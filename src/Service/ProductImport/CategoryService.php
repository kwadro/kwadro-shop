<?php

namespace App\Service\ProductImport;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use SimpleXMLElement;

readonly class CategoryService
{

    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private GeneratorSlugService $generatorSlugService
    ) {
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
