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

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductAttributeGroupRepository $groupRepository,
        private readonly ProductAttributeRepository $attributeRepository,
        private readonly GeneratorSlugService $generatorSlugService,
    ) {
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
            foreach ($info['params'] as $paramName => $stats) {
                $attribute = $this->findAttributeInGroup($group, $paramName);
                if ($attribute === null) {
                    $attribute = (new ProductAttribute())->setName($paramName);
                    $group->addAttribute($attribute);
                    $this->em->persist($attribute);
                }

                $dataType = $this->inferDataType($stats);
                $showInFilter = $this->inferShowInFilter($stats, $dataType);
                $useInSearch = $this->inferUseInSearch($dataType, $showInFilter);

                $attribute
                    ->setName($paramName)
                    ->setDataType($dataType)
                    ->setShowInFilter($showInFilter)
                    ->setUseInSearch($useInSearch)
                    ->setPosition($attrPosition);
                $this->assignUniqueAttributeSlug(
                    $attribute,
                    $this->generatorSlugService->transferCategoryNameToSlug($paramName),
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
            $name = trim((string) ($param['name'] ?? ''));
            $raw = trim((string) ($param['value'] ?? ''));
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
     * @return array<int, array{params: array<string, array{count: int, int: int, decimal: int, long: int, uniques: array<string, true>}>}>
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
                    $name = trim((string) $param['name']);
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
     * @param array{count: int, int: int, decimal: int, long: int, uniques: array<string, true>} $stats
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
     * @param array{count: int, int: int, decimal: int, long: int, uniques: array<string, true>} $stats
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
