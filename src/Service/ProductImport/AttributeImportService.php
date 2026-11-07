<?php

namespace App\Service\ProductImport;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductAttributeDataType;
use App\Entity\ProductAttributeGroup;
use App\Entity\ProductAttributeValue;
use App\Repository\CategoryRepository;
use App\Repository\ProductAttributeGroupRepository;
use App\Repository\ProductAttributeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds attribute groups from YML &lt;param&gt; sets (one group per feed category)
 * and assigns product attribute values during import.
 */
class AttributeImportService
{
    /** @var array<string, ProductAttribute> key: "{externalCategoryId}|{paramName}" */
    private array $attributeIndex = [];

    /**
     * Key "{categoryId}|{attributeId}|{feed value}" => storefront value.
     * Zero category or attribute id means the rule is not limited on that axis.
     *
     * @var array<string, string>
     */
    private array $paramValueReplacementRules = [];

    /** @var array<string, int>|null key: "{externalCategoryId}|{attributeName}" */
    private ?array $attributeIdByKey = null;

    /** @var array<string, string> Feed attribute name => storefront name. Always global. */
    private array $paramNameReplacements = [];

    /** @var array<int, int>|null Feed external category id => store category id of its attribute group. */
    private ?array $categoryIdByExternalId = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductAttributeGroupRepository $groupRepository,
        private readonly ProductAttributeRepository $attributeRepository,
        private readonly GeneratorSlugService $generatorSlugService,
    ) {
    }

    /**
     * @param list<array{from?: string, to?: string, category_id?: int|null, attribute_id?: int|null}> $rows
     */
    public function setParamValueReplacements(array $rows): void
    {
        $rules = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $from = trim((string) ($row['from'] ?? ''));
            $to = trim((string) ($row['to'] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            $rules[$this->replacementKey($categoryId > 0 ? $categoryId : 0, $attributeId > 0 ? $attributeId : 0, $from)] = $to;
        }
        $this->paramValueReplacementRules = $rules;
        $this->categoryIdByExternalId = null;
        $this->attributeIdByKey = null;
    }

    /**
     * @param array<string, string> $replacements Feed attribute name => storefront name.
     */
    public function setParamNameReplacements(array $replacements): void
    {
        $map = [];
        foreach ($replacements as $from => $to) {
            $from = trim((string) $from);
            $to = trim((string) $to);
            if ($from === '' || $to === '') {
                continue;
            }
            $map[$from] = $to;
        }
        $this->paramNameReplacements = $map;
    }

    /**
     * Analyze the feed, create/update attribute groups and their attributes.
     *
     * @return array{groups: int, attributes: int}
     */
    public function syncGroupsFromFeed(string $localPath, bool $dryRun = false): array
    {
        $analysis = $this->analyzeFeed($localPath);
        $groupsCreatedOrUpdated = 0;
        $attributesCreatedOrUpdated = 0;
        $position = 1;

        foreach ($analysis as $externalCategoryId => $info) {
            $category = $this->categoryRepository->findOneBy(['external_id' => $externalCategoryId]);
            $groupName = $category?->getName() ?: sprintf('Category %d', $externalCategoryId);
            $group = $this->groupRepository->findOneByExternalCategoryId($externalCategoryId);
            if ($group === null) {
                $group = (new ProductAttributeGroup())
                    ->setExternalCategoryId($externalCategoryId)
                    ->setPosition($position);
                $this->em->persist($group);
            }

            $group
                ->setName($groupName)
                ->setCategory($category)
                ->setPosition($position);
            $this->assignUniqueGroupSlug($group, $this->generatorSlugService->transferCategoryNameToSlug($groupName));
            ++$groupsCreatedOrUpdated;

            $attrPosition = 1;
            foreach ($info['params'] as $storeName => $stats) {
                $feedName = (string) ($stats['feed_name'] ?? $storeName);
                $attribute = $this->findAttributeInGroup($group, $feedName);
                if ($attribute === null && $storeName !== $feedName) {
                    $attribute = $this->findAttributeInGroup($group, $storeName);
                }
                $isNew = $attribute === null;
                if ($attribute === null) {
                    $attribute = (new ProductAttribute())->setName($storeName);
                    $group->addAttribute($attribute);
                    $this->em->persist($attribute);
                }

                $dataType = $this->inferDataType($stats);
                $attribute
                    ->setName($storeName)
                    ->setDataType($dataType)
                    ->setPosition($attrPosition);
                // Keep a manual "show in filter" choice. Only new attributes get the inferred value.
                if ($isNew) {
                    $showInFilter = $this->inferShowInFilter($stats, $dataType);
                    $attribute
                        ->setShowInFilter($showInFilter)
                        ->setUseInSearch($this->inferUseInSearch($dataType, $showInFilter));
                }
                $this->assignUniqueAttributeSlug(
                    $attribute,
                    $this->generatorSlugService->transferCategoryNameToSlug($feedName),
                );
                ++$attributesCreatedOrUpdated;
                ++$attrPosition;
            }

            ++$position;
            if (!$dryRun && ($position % 25) === 0) {
                $this->em->flush();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $this->rebuildAttributeIndex();

        return [
            'groups' => $groupsCreatedOrUpdated,
            'attributes' => $attributesCreatedOrUpdated,
        ];
    }

    public function rebuildAttributeIndex(): void
    {
        $this->attributeIndex = [];
        $attributes = $this->attributeRepository->createQueryBuilder('a')
            ->innerJoin('a.group', 'g')
            ->addSelect('g')
            ->getQuery()
            ->getResult();

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof ProductAttribute) {
                continue;
            }
            $externalId = $attribute->getGroup()?->getExternalCategoryId();
            if ($externalId === null) {
                continue;
            }
            $this->attributeIndex[$externalId.'|'.$attribute->getName()] = $attribute;
        }
        $this->attributeIdByKey = null;
    }

    /**
     * @param list<array{name: string, value: string}> $params
     */
    public function syncProductValues(Product $product, int $externalCategoryId, array $params): void
    {
        if ($this->attributeIndex === []) {
            $this->rebuildAttributeIndex();
        }

        // One offer may repeat the same <param name> — keep the last value.
        $uniqueParams = [];
        foreach ($params as $param) {
            $name = $this->normalizeParamName(trim((string) ($param['name'] ?? '')));
            $raw = $this->normalizeParamValue(trim((string) ($param['value'] ?? '')), $externalCategoryId, $name);
            if ($name === '' || $raw === '') {
                continue;
            }
            $uniqueParams[$name] = $raw;
        }

        /** @var array<int, ProductAttributeValue> $existingByAttrId */
        $existingByAttrId = [];
        // Lazy-load once via the association (no extra findBy per product).
        foreach ($product->getAttributeValues() as $value) {
            $attrId = $value->getAttribute()?->getId();
            if ($attrId === null) {
                continue;
            }
            // Drop duplicate rows for the same attribute if they already exist.
            if (isset($existingByAttrId[$attrId])) {
                $product->removeAttributeValue($value);
                continue;
            }
            $existingByAttrId[$attrId] = $value;
        }

        $seenAttrIds = [];
        foreach ($uniqueParams as $name => $raw) {
            $attribute = $this->attributeIndex[$externalCategoryId.'|'.$name] ?? null;
            if (!$attribute instanceof ProductAttribute) {
                continue;
            }
            $attrId = $attribute->getId();
            if ($attrId === null) {
                continue;
            }

            $value = $existingByAttrId[$attrId] ?? null;
            if ($value === null) {
                $value = (new ProductAttributeValue())->setAttribute($attribute);
                $product->addAttributeValue($value);
                $existingByAttrId[$attrId] = $value;
            }
            $value->setFromRaw($raw, $attribute->getDataType());
            $seenAttrIds[$attrId] = true;
        }

        foreach ($existingByAttrId as $attrId => $value) {
            if (!isset($seenAttrIds[$attrId])) {
                $product->removeAttributeValue($value);
            }
        }
    }

    /**
     * @return array<int, array{params: array<string, array{count: int, int: int, decimal: int, long: int, uniques: array<string, true>, feed_name: string}>}>
     */
    private function analyzeFeed(string $localPath): array
    {
        $reader = new \XMLReader();
        if (!$reader->open($localPath)) {
            throw new \RuntimeException(sprintf('Cannot open feed for attributes: %s', $localPath));
        }

        $byCategory = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'offer') {
                    continue;
                }
                $node = simplexml_load_string($reader->readOuterXml());
                if ($node === false) {
                    continue;
                }
                if (!(bool) $node['available']) {
                    continue;
                }

                $categoryId = (int) $node->categoryId;
                if ($categoryId < 1) {
                    continue;
                }
                if (!isset($byCategory[$categoryId])) {
                    $byCategory[$categoryId] = ['params' => []];
                }

                if (!isset($node->param)) {
                    continue;
                }

                foreach ($node->param as $param) {
                    $feedName = trim((string) $param['name']);
                    $name = $this->normalizeParamName($feedName);
                    $value = $this->normalizeParamValue(trim((string) $param), $categoryId, $name);
                    if ($name === '' || $value === '') {
                        continue;
                    }
                    if (!isset($byCategory[$categoryId]['params'][$name])) {
                        $byCategory[$categoryId]['params'][$name] = [
                            'count' => 0,
                            'int' => 0,
                            'decimal' => 0,
                            'long' => 0,
                            'uniques' => [],
                            'feed_name' => $feedName,
                        ];
                    }
                    $stats = &$byCategory[$categoryId]['params'][$name];
                    ++$stats['count'];
                    if (preg_match('/^-?\d+$/u', $value) === 1) {
                        ++$stats['int'];
                    } elseif (preg_match('/^-?\d+[.,]\d+$/u', $value) === 1) {
                        ++$stats['decimal'];
                    }
                    if (mb_strlen($value) > 120) {
                        ++$stats['long'];
                    }
                    if (\count($stats['uniques']) < 80) {
                        $stats['uniques'][$value] = true;
                    }
                    unset($stats);
                }
            }
        } finally {
            $reader->close();
        }

        return $byCategory;
    }

    /**
     * @param array{count: int, int: int, decimal: int, long: int, uniques: array<string, true>, feed_name?: string} $stats
     */
    private function inferDataType(array $stats): ProductAttributeDataType
    {
        $count = max(1, $stats['count']);
        if (($stats['long'] / $count) >= 0.3) {
            return ProductAttributeDataType::Text;
        }
        if (($stats['int'] / $count) >= 0.7) {
            return ProductAttributeDataType::Int;
        }
        if ((($stats['int'] + $stats['decimal']) / $count) >= 0.7) {
            return ProductAttributeDataType::Decimal;
        }

        return ProductAttributeDataType::Varchar;
    }

    /**
     * @param array{count: int, int: int, decimal: int, long: int, uniques: array<string, true>, feed_name?: string} $stats
     */
    private function inferShowInFilter(array $stats, ProductAttributeDataType $dataType): bool
    {
        if ($dataType === ProductAttributeDataType::Text) {
            return false;
        }

        $uniqueCount = \count($stats['uniques']);
        if ($uniqueCount > 50) {
            return false;
        }
        if ($stats['count'] >= 20 && $uniqueCount >= (int) floor($stats['count'] * 0.85)) {
            return false;
        }

        return $uniqueCount >= 2;
    }

    private function inferUseInSearch(ProductAttributeDataType $dataType, bool $showInFilter): bool
    {
        return $dataType === ProductAttributeDataType::Varchar && $showInFilter;
    }

    private function normalizeParamName(string $name): string
    {
        if ($name === '') {
            return $name;
        }

        return $this->paramNameReplacements[$name] ?? $name;
    }

    private function normalizeParamValue(string $value, int $externalCategoryId, string $attributeName): string
    {
        if ($value === '' || $this->paramValueReplacementRules === []) {
            return $value;
        }

        $categoryId = $this->categoryIdForExternal($externalCategoryId) ?? 0;
        $attributeId = $attributeName !== '' ? ($this->attributeIdFor($externalCategoryId, $attributeName) ?? 0) : 0;
        foreach ($this->replacementCandidates($categoryId, $attributeId, $value) as $key) {
            if (isset($this->paramValueReplacementRules[$key])) {
                return $this->paramValueReplacementRules[$key];
            }
        }

        return $value;
    }

    private function replacementKey(int $categoryId, int $attributeId, string $value): string
    {
        return $categoryId.'|'.$attributeId.'|'.$value;
    }

    /**
     * More specific rules win: category and attribute, then attribute, then category, then global.
     *
     * @return list<string>
     */
    private function replacementCandidates(int $categoryId, int $attributeId, string $value): array
    {
        $keys = [];
        if ($categoryId > 0 && $attributeId > 0) {
            $keys[] = $this->replacementKey($categoryId, $attributeId, $value);
        }
        if ($attributeId > 0) {
            $keys[] = $this->replacementKey(0, $attributeId, $value);
        }
        if ($categoryId > 0) {
            $keys[] = $this->replacementKey($categoryId, 0, $value);
        }
        $keys[] = $this->replacementKey(0, 0, $value);

        return $keys;
    }

    private function attributeIdFor(int $externalCategoryId, string $name): ?int
    {
        $key = $externalCategoryId.'|'.$name;
        $indexed = $this->attributeIndex[$key] ?? null;
        if ($indexed instanceof ProductAttribute && $indexed->getId() !== null) {
            return $indexed->getId();
        }
        if ($this->attributeIdByKey === null) {
            $this->attributeIdByKey = $this->loadAttributeIds();
        }

        return $this->attributeIdByKey[$key] ?? null;
    }

    /**
     * @return array<string, int>
     */
    private function loadAttributeIds(): array
    {
        $rows = $this->attributeRepository->createQueryBuilder('a')
            ->select('a.id AS id, a.name AS name, g.external_category_id AS external_id')
            ->innerJoin('a.group', 'g')
            ->andWhere('g.external_category_id IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $externalId = (int) ($row['external_id'] ?? 0);
            $id = (int) ($row['id'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            if ($externalId < 1 || $id < 1 || $name === '') {
                continue;
            }
            $map[$externalId.'|'.$name] = $id;
        }

        return $map;
    }

    private function categoryIdForExternal(int $externalCategoryId): ?int
    {
        if ($this->categoryIdByExternalId === null) {
            $this->categoryIdByExternalId = $this->loadCategoryIdByExternalId();
        }

        return $this->categoryIdByExternalId[$externalCategoryId] ?? null;
    }

    /**
     * The attribute group linked to a store category wins. A feed category that
     * does not have a group yet falls back to the store category with the same external id.
     *
     * @return array<int, int>
     */
    private function loadCategoryIdByExternalId(): array
    {
        $map = [];
        $groups = $this->groupRepository->createQueryBuilder('g')
            ->select('g.external_category_id AS external_id, IDENTITY(g.category) AS category_id')
            ->andWhere('g.external_category_id IS NOT NULL')
            ->getQuery()
            ->getArrayResult();
        foreach ($groups as $row) {
            $externalId = (int) ($row['external_id'] ?? 0);
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($externalId > 0 && $categoryId > 0) {
                $map[$externalId] = $categoryId;
            }
        }

        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->select('c.id AS id, c.external_id AS external_id')
            ->andWhere('c.external_id IS NOT NULL')
            ->getQuery()
            ->getArrayResult();
        foreach ($categories as $row) {
            $externalId = (int) ($row['external_id'] ?? 0);
            $categoryId = (int) ($row['id'] ?? 0);
            if ($externalId > 0 && $categoryId > 0 && !isset($map[$externalId])) {
                $map[$externalId] = $categoryId;
            }
        }

        return $map;
    }

    private function findAttributeInGroup(ProductAttributeGroup $group, string $name): ?ProductAttribute
    {
        foreach ($group->getAttributes() as $attribute) {
            if ($attribute->getName() === $name) {
                return $attribute;
            }
        }

        if ($group->getId() === null) {
            return null;
        }

        return $this->attributeRepository->findOneBy([
            'group' => $group,
            'name' => $name,
        ]);
    }

    private function assignUniqueGroupSlug(ProductAttributeGroup $group, string $baseSlug): void
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'attr-group';
        $candidate = $slug;
        $i = 2;
        while ($this->groupRepository->slugExists($candidate, $group->getId())) {
            $candidate = $slug.'-'.$i;
            ++$i;
        }
        $group->setSlug($candidate);
    }

    private function assignUniqueAttributeSlug(ProductAttribute $attribute, string $baseSlug): void
    {
        $group = $attribute->getGroup();
        $groupId = $group?->getId();
        $slug = $baseSlug !== '' ? $baseSlug : 'attr';
        if ($groupId === null) {
            // New group not flushed yet — ensure uniqueness inside the collection.
            $candidate = $slug;
            $i = 2;
            $existing = [];
            foreach ($group?->getAttributes() ?? [] as $sibling) {
                if ($sibling === $attribute) {
                    continue;
                }
                $existing[$sibling->getSlug()] = true;
            }
            while (isset($existing[$candidate])) {
                $candidate = $slug.'-'.$i;
                ++$i;
            }
            $attribute->setSlug($candidate);

            return;
        }

        $candidate = $slug;
        $i = 2;
        while ($this->attributeRepository->slugExistsInGroup($groupId, $candidate, $attribute->getId())) {
            $candidate = $slug.'-'.$i;
            ++$i;
        }
        $attribute->setSlug($candidate);
    }
}
