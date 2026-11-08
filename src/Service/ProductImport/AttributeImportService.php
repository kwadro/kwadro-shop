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
    /**
     * Lightweight index: survives EntityManager::clear() without a full reload.
     *
     * @var array<string, array{id: int, dataType: ProductAttributeDataType}>
     */
    private array $attributeMeta = [];

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

        $categoriesByExternalId = $this->loadCategoriesByExternalId();
        $groupsByExternalId = $this->loadGroupsWithAttributesByExternalId();
        $usedGroupSlugs = $this->loadUsedGroupSlugs();
        $usedAttributeSlugsByGroup = $this->loadUsedAttributeSlugsByGroupId();

        foreach ($analysis as $externalCategoryId => $info) {
            $category = $categoriesByExternalId[$externalCategoryId] ?? null;
            $groupName = $category?->getName() ?: sprintf('Category %d', $externalCategoryId);
            $group = $groupsByExternalId[$externalCategoryId] ?? null;
            if ($group === null) {
                $group = (new ProductAttributeGroup())
                    ->setExternalCategoryId($externalCategoryId)
                    ->setPosition($position);
                $this->em->persist($group);
                $groupsByExternalId[$externalCategoryId] = $group;
            }

            $group
                ->setName($groupName)
                ->setCategory($category)
                ->setPosition($position);
            $this->assignUniqueGroupSlugCached(
                $group,
                $this->generatorSlugService->transferCategoryNameToSlug($groupName),
                $usedGroupSlugs,
            );
            ++$groupsCreatedOrUpdated;

            /** @var array<string, ProductAttribute> $attrsByName */
            $attrsByName = [];
            foreach ($group->getAttributes() as $existingAttr) {
                $attrsByName[$existingAttr->getName()] = $existingAttr;
            }

            $groupId = $group->getId();
            if ($groupId !== null) {
                if (!isset($usedAttributeSlugsByGroup[$groupId])) {
                    $usedAttributeSlugsByGroup[$groupId] = [];
                }
                $attrSlugBucket = &$usedAttributeSlugsByGroup[$groupId];
            } else {
                $attrSlugBucket = [];
            }

            $attrPosition = 1;
            foreach ($info['params'] as $storeName => $stats) {
                $feedName = (string) ($stats['feed_name'] ?? $storeName);
                $attribute = $attrsByName[$feedName] ?? $attrsByName[$storeName] ?? null;
                $isNew = $attribute === null;
                if ($attribute === null) {
                    $attribute = (new ProductAttribute())->setName($storeName);
                    $group->addAttribute($attribute);
                    $this->em->persist($attribute);
                    $attrsByName[$storeName] = $attribute;
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
                $this->assignUniqueAttributeSlugCached(
                    $attribute,
                    $this->generatorSlugService->transferCategoryNameToSlug($feedName),
                    $attrSlugBucket,
                );
                ++$attributesCreatedOrUpdated;
                ++$attrPosition;
            }
            unset($attrSlugBucket);

            ++$position;
            if (!$dryRun && ($position % 25) === 0) {
                $this->em->flush();
                // New groups get ids after flush — keep slug bucket keys aligned.
                foreach ($groupsByExternalId as $syncedGroup) {
                    $syncedGroupId = $syncedGroup->getId();
                    if ($syncedGroupId === null || isset($usedAttributeSlugsByGroup[$syncedGroupId])) {
                        continue;
                    }
                    $bucket = [];
                    foreach ($syncedGroup->getAttributes() as $syncedAttr) {
                        $slug = $syncedAttr->getSlug();
                        if ($slug !== '') {
                            $bucket[$slug] = true;
                        }
                    }
                    $usedAttributeSlugsByGroup[$syncedGroupId] = $bucket;
                }
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

    /**
     * Rebuild the lightweight attribute meta index from the database.
     * Safe to call after syncGroupsFromFeed; not required after EntityManager::clear().
     */
    public function rebuildAttributeIndex(): void
    {
        $rows = $this->attributeRepository->createQueryBuilder('a')
            ->select('a.id AS id, a.name AS name, a.data_type AS data_type, g.external_category_id AS external_id')
            ->innerJoin('a.group', 'g')
            ->andWhere('g.external_category_id IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $this->attributeMeta = [];
        $attributeIds = [];
        foreach ($rows as $row) {
            $externalId = (int) ($row['external_id'] ?? 0);
            $id = (int) ($row['id'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            if ($externalId < 1 || $id < 1 || $name === '') {
                continue;
            }
            $dataType = $row['data_type'] ?? ProductAttributeDataType::Varchar;
            if (!$dataType instanceof ProductAttributeDataType) {
                $dataType = ProductAttributeDataType::tryFrom((string) $dataType) ?? ProductAttributeDataType::Varchar;
            }
            $this->attributeMeta[$externalId.'|'.$name] = [
                'id' => $id,
                'dataType' => $dataType,
            ];
            $attributeIds[$externalId.'|'.$name] = $id;
        }
        $this->attributeIdByKey = $attributeIds;
    }

    /**
     * @param list<array{name: string, value: string}> $params
     */
    public function syncProductValues(Product $product, int $externalCategoryId, array $params): void
    {
        if ($this->attributeMeta === []) {
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
        // Prefer already-eager-loaded association (no extra findBy per product).
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
            $meta = $this->attributeMeta[$externalCategoryId.'|'.$name] ?? null;
            if ($meta === null) {
                continue;
            }
            $attrId = $meta['id'];

            $value = $existingByAttrId[$attrId] ?? null;
            if ($value === null) {
                /** @var ProductAttribute $attribute */
                $attribute = $this->em->getReference(ProductAttribute::class, $attrId);
                $value = (new ProductAttributeValue())->setAttribute($attribute);
                $product->addAttributeValue($value);
                $existingByAttrId[$attrId] = $value;
            }
            $value->setFromRaw($raw, $meta['dataType']);
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
                    // Name replacements only — value replacements are applied later per product.
                    $name = $this->normalizeParamName($feedName);
                    $value = trim((string) $param);
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
        $meta = $this->attributeMeta[$key] ?? null;
        if ($meta !== null) {
            return $meta['id'];
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

    /**
     * @return array<int, \App\Entity\Category>
     */
    private function loadCategoriesByExternalId(): array
    {
        $rows = $this->categoryRepository->createQueryBuilder('c')
            ->andWhere('c.external_id IS NOT NULL')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $category) {
            $externalId = $category->getExternalId();
            if ($externalId === null) {
                continue;
            }
            $map[(int) $externalId] = $category;
        }

        return $map;
    }

    /**
     * @return array<int, ProductAttributeGroup>
     */
    private function loadGroupsWithAttributesByExternalId(): array
    {
        $groups = $this->groupRepository->createQueryBuilder('g')
            ->leftJoin('g.attributes', 'a')
            ->addSelect('a')
            ->andWhere('g.external_category_id IS NOT NULL')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($groups as $group) {
            if (!$group instanceof ProductAttributeGroup) {
                continue;
            }
            $externalId = $group->getExternalCategoryId();
            if ($externalId === null) {
                continue;
            }
            $map[(int) $externalId] = $group;
        }

        return $map;
    }

    /**
     * @return array<string, true>
     */
    private function loadUsedGroupSlugs(): array
    {
        $rows = $this->groupRepository->createQueryBuilder('g')
            ->select('g.slug AS slug')
            ->getQuery()
            ->getArrayResult();

        $used = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '') {
                $used[$slug] = true;
            }
        }

        return $used;
    }

    /**
     * @return array<int, array<string, true>>
     */
    private function loadUsedAttributeSlugsByGroupId(): array
    {
        $rows = $this->attributeRepository->createQueryBuilder('a')
            ->select('IDENTITY(a.group) AS group_id, a.slug AS slug')
            ->getQuery()
            ->getArrayResult();

        $used = [];
        foreach ($rows as $row) {
            $groupId = (int) ($row['group_id'] ?? 0);
            $slug = (string) ($row['slug'] ?? '');
            if ($groupId < 1 || $slug === '') {
                continue;
            }
            $used[$groupId][$slug] = true;
        }

        return $used;
    }

    /**
     * @param array<string, true> $usedGroupSlugs
     */
    private function assignUniqueGroupSlugCached(
        ProductAttributeGroup $group,
        string $baseSlug,
        array &$usedGroupSlugs,
    ): void {
        $slug = $baseSlug !== '' ? $baseSlug : 'attr-group';
        $current = $group->getSlug();
        if ($current !== '') {
            unset($usedGroupSlugs[$current]);
        }

        $candidate = $slug;
        $i = 2;
        while (isset($usedGroupSlugs[$candidate])) {
            $candidate = $slug.'-'.$i;
            ++$i;
        }
        $usedGroupSlugs[$candidate] = true;
        $group->setSlug($candidate);
    }

    /**
     * @param array<string, true> $usedAttributeSlugs
     */
    private function assignUniqueAttributeSlugCached(
        ProductAttribute $attribute,
        string $baseSlug,
        array &$usedAttributeSlugs,
    ): void {
        $slug = $baseSlug !== '' ? $baseSlug : 'attr';
        $current = $attribute->getSlug();
        if ($current !== '') {
            unset($usedAttributeSlugs[$current]);
        }

        $candidate = $slug;
        $i = 2;
        while (isset($usedAttributeSlugs[$candidate])) {
            $candidate = $slug.'-'.$i;
            ++$i;
        }
        $usedAttributeSlugs[$candidate] = true;
        $attribute->setSlug($candidate);
    }
}
