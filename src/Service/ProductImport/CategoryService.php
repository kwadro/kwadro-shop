<?php

namespace App\Service\ProductImport;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImport;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use SimpleXMLElement;

readonly class CategoryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository,
        private GeneratorSlugService $generatorSlugService,
    ) {
    }

    /**
     * After import: disable the configured categories and their products.
     * Categories removed from that list since the previous import are enabled again,
     * together with their products. SKUs from the import disable list stay off.
     * In delete mode, products missing from the file stay off.
     *
     * @param array<string, true>|null $seenSkus Lowercase SKUs present in this file. Null keeps every product of a restored category.
     *
     * @return array{categories: int, products: int, categories_on: int, products_on: int}
     */
    public function disableConfiguredAfterImport(ProductImport $import, bool $dryRun = false, ?array $seenSkus = null): array
    {
        $result = ['categories' => 0, 'products' => 0, 'categories_on' => 0, 'products_on' => 0];
        $activeSlugs = $import->isDisableCategoriesAfterImport() ? $import->getDisabledCategorySlugs() : [];
        $removedSlugs = array_values(array_diff($import->getAppliedDisabledCategorySlugs(), $activeSlugs));
        $disabledSkuMap = array_fill_keys(array_map('mb_strtolower', $import->getDisabledSkus()), true);

        if ($removedSlugs !== []) {
            $restored = $this->enableCategoriesAndProducts($removedSlugs, $activeSlugs, $disabledSkuMap, $seenSkus);
            $result['categories_on'] = $restored['categories'];
            $result['products_on'] = $restored['products'];
        }

        if ($activeSlugs !== []) {
            $disabled = $this->disableCategoriesAndProducts($activeSlugs);
            $result['categories'] = $disabled['categories'];
            $result['products'] = $disabled['products'];
        }

        if (!$dryRun) {
            $import->setAppliedDisabledCategorySlugs($activeSlugs);
            $this->em->flush();
        }

        return $result;
    }

    /**
     * @param list<string> $slugs
     *
     * @return array{categories: int, products: int}
     */
    private function disableCategoriesAndProducts(array $slugs): array
    {
        $result = ['categories' => 0, 'products' => 0];
        $categories = $this->categoriesBySlugs($slugs);
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = (int) $category->getId();
            if ($category->isEnabled()) {
                $category->setEnabled(false);
                ++$result['categories'];
            }
        }
        if ($categoryIds === []) {
            return $result;
        }

        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.id IN (:categoryIds)')
            ->andWhere('p.enabled = true')
            ->setParameter('categoryIds', $categoryIds)
            ->getQuery()
            ->getResult();

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }
            $product->setEnabled(false);
            ++$result['products'];
        }

        return $result;
    }

    /**
     * @param list<string> $removedSlugs
     * @param list<string> $stillDisabledSlugs
     * @param array<string, true> $disabledSkuMap
     * @param array<string, true>|null $seenSkus
     *
     * @return array{categories: int, products: int}
     */
    private function enableCategoriesAndProducts(array $removedSlugs, array $stillDisabledSlugs, array $disabledSkuMap, ?array $seenSkus): array
    {
        $result = ['categories' => 0, 'products' => 0];
        $categories = $this->categoriesBySlugs($removedSlugs);
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = (int) $category->getId();
            if (!$category->isEnabled()) {
                $category->setEnabled(true);
                ++$result['categories'];
            }
        }
        if ($categoryIds === []) {
            return $result;
        }

        $blockedSlugs = array_fill_keys($stillDisabledSlugs, true);
        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.id IN (:categoryIds)')
            ->andWhere('p.enabled = false')
            ->setParameter('categoryIds', $categoryIds)
            ->getQuery()
            ->getResult();

        foreach ($products as $product) {
            if (!$product instanceof Product || !$this->canRestoreProduct($product, $blockedSlugs, $disabledSkuMap, $seenSkus)) {
                continue;
            }
            $product->setEnabled(true);
            ++$result['products'];
        }

        return $result;
    }

    /**
     * @param array<string, true> $blockedSlugs
     * @param array<string, true> $disabledSkuMap
     * @param array<string, true>|null $seenSkus
     */
    private function canRestoreProduct(Product $product, array $blockedSlugs, array $disabledSkuMap, ?array $seenSkus): bool
    {
        $sku = mb_strtolower($product->getSku());
        if (isset($disabledSkuMap[$sku])) {
            return false;
        }
        if ($seenSkus !== null && !isset($seenSkus[$sku])) {
            return false;
        }
        foreach ($product->getCategories() as $category) {
            if (isset($blockedSlugs[$category->getSlug()])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $slugs
     *
     * @return list<Category>
     */
    private function categoriesBySlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->andWhere('c.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->getQuery()
            ->getResult();

        $list = [];
        foreach ($categories as $category) {
            if ($category instanceof Category && $category->getId() !== null) {
                $list[] = $category;
            }
        }

        return $list;
    }

    public function update(
        SimpleXMLElement $node,
        bool $dryRun = false,
        ?int $baseCategoryId = null,
        bool $status = false,
        int $position = 1,
        array $nameReplacements = [],
    ): void {
        if (!$this->em->isOpen()) {
            throw new \RuntimeException(
                'EntityManager is closed (previous category flush failed, often a duplicate slug).'
            );
        }

        $externalId = (int)$node['id'];
        $rawParentId = isset($node['parentId']) ? (int)$node['parentId'] : null;
        $parentExternalId = $rawParentId !== null ? $this->fixErrorId($rawParentId) : null;

        $feedName = trim((string) $node);
        $categoryName = $nameReplacements[$feedName] ?? $feedName;
        $baseSlug = $this->generatorSlugService->transferCategoryNameToSlug($feedName);

        $category = $this->categoryRepository->findOneBy(['external_id' => $externalId]);
        if ($category === null) {
            $category = new Category();
            $category->setExternalId($externalId);

            $showFilters = false;
            if ($parentExternalId !== null) {
                $parent = $this->categoryRepository->findOneBy(['external_id' => $parentExternalId]);
                if (!$parent instanceof Category && $baseCategoryId !== null) {
                    $parent = $this->categoryRepository->find($baseCategoryId);
                }
                if ($parent instanceof Category) {
                    $category->setParent($parent);
                    $showFilters = true;
                }
            } elseif ($baseCategoryId !== null) {
                $parent = $this->categoryRepository->find($baseCategoryId);
                if ($parent instanceof Category) {
                    $category->setParent($parent);
                    $showFilters = true;
                }
            }

            $category->setShowFilters($showFilters);
        }

        $category->setName($categoryName);
        $this->generatorSlugService->assignUniqueSlug($category, $baseSlug);
        $category->setPosition($position);
        $category->setEnabled($status);
        $category->setShowOutOfStock(false);
        $category->recalculateLevel();

        if ($dryRun) {
            return;
        }

        try {
            $this->em->persist($category);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new \RuntimeException(
                sprintf(
                    'Category slug conflict for external_id=%d name="%s" slug="%s": %s',
                    $externalId,
                    $categoryName,
                    $category->getSlug(),
                    $e->getMessage(),
                ), 0, $e
            );
        }
    }

    private function fixErrorId(int $externalId): int
    {
        if ($externalId === 135) {
            return 58;
        }

        return $externalId;
    }
}
