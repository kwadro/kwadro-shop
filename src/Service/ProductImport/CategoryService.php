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
     * After import: disable configured category slugs and every product linked to them.
     *
     * @return array{categories: int, products: int}
     */
    public function disableConfiguredAfterImport(ProductImport $import, bool $dryRun = false): array
    {
        $result = ['categories' => 0, 'products' => 0];
        $slugs = $import->getDisabledCategorySlugs();
        if (!$import->isDisableCategoriesAfterImport() || $slugs === []) {
            return $result;
        }

        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->andWhere('c.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->getQuery()
            ->getResult();

        $categoryIds = [];
        foreach ($categories as $category) {
            if (!$category instanceof Category) {
                continue;
            }
            $categoryIds[] = (int) $category->getId();
            if ($category->isEnabled()) {
                $category->setEnabled(false);
                ++$result['categories'];
            }
        }

        if ($categoryIds === []) {
            if (!$dryRun) {
                $this->em->flush();
            }

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

        if (!$dryRun) {
            $this->em->flush();
        }

        return $result;
    }

    public function update(
        SimpleXMLElement $node,
        bool $dryRun = false,
        ?int $baseCategoryId = null,
        bool $status = false,
        int $position = 1,
    ): void {
        if (!$this->em->isOpen()) {
            throw new \RuntimeException(
                'EntityManager is closed (previous category flush failed, often a duplicate slug).'
            );
        }

        $externalId = (int)$node['id'];
        $rawParentId = isset($node['parentId']) ? (int)$node['parentId'] : null;
        $parentExternalId = $rawParentId !== null ? $this->fixErrorId($rawParentId) : null;

        $categoryName = trim((string)$node);
        $baseSlug = $this->generatorSlugService->transferCategoryNameToSlug($categoryName);

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
