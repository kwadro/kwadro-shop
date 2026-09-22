<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Category> */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findDefaultItem(): ?Category
    {
        return $this->findOneBy(['slug' => Category::DEFAULT_SLUG])
            ?? $this->findOneBy(['name' => Category::DEFAULT_NAME]);
    }

    public function findOrCreateDefault(): Category
    {
        $existing = $this->findDefaultItem();
        if ($existing instanceof Category) {
            return $existing;
        }

        $category = (new Category())
            ->setName(Category::DEFAULT_NAME)
            ->setSlug(Category::DEFAULT_SLUG)
            ->setLevel(0)
            ->setPosition(0)
            ->setParent(null);

        $em = $this->getEntityManager();
        $em->persist($category);
        $em->flush();

        return $category;
    }

    /**
     * @return list<Category>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> categoryId => product count
     */
    public function countProductsGroupedByCategoryId(): array
    {
        if (!$this->getEntityManager()->getConnection()->createSchemaManager()->tablesExist(['shop_product_category'])) {
            return [];
        }

        $raw = $this->getEntityManager()->getConnection()->fetchAllAssociative(<<<'SQL'
            SELECT category_id AS category_id, COUNT(product_id) AS product_count
            FROM shop_product_category
            GROUP BY category_id
        SQL);

        $counts = [];
        foreach ($raw as $row) {
            $counts[(int) $row['category_id']] = (int) $row['product_count'];
        }

        return $counts;
    }

    /**
     * Nested tree for Magento-like admin UI.
     *
     * @return list<array{
     *   id: int,
     *   name: string,
     *   slug: string,
     *   level: int,
     *   position: int,
     *   isDefault: bool,
     *   productCount: int,
     *   children: list
     * }>
     */
    public function buildAdminTree(): array
    {
        $categories = $this->findAllOrdered();
        $productCounts = $this->countProductsGroupedByCategoryId();

        /** @var array<int, array<string, mixed>> $nodes */
        $nodes = [];
        foreach ($categories as $category) {
            $id = $category->getId();
            if ($id === null) {
                continue;
            }

            $nodes[$id] = [
                'id' => $id,
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'level' => $category->getLevel(),
                'position' => $category->getPosition(),
                'isDefault' => $category->isDefault(),
                'enabled' => $category->isEnabled(),
                'productCount' => $productCounts[$id] ?? 0,
                'parentId' => $category->getParent()?->getId(),
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $node['parentId'];
            unset($node['parentId']);
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }

    /**
     * Two-level menu under Default for the shop header.
     *
     * @return list<array{id: int, name: string, slug: string, children: list<array{id: int, name: string, slug: string}>}>
     */
    public function buildStorefrontMenuTree(): array
    {
        $default = $this->findDefaultItem();
        if ($default === null) {
            return [];
        }

        /** @var list<Category> $levelOne */
        $levelOne = $this->createQueryBuilder('c')
            ->andWhere('c.parent = :default')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('default', $default)
            ->setParameter('enabled', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        if ($levelOne === []) {
            return [];
        }

        $parentIds = array_values(array_filter(array_map(
            static fn (Category $category): ?int => $category->getId(),
            $levelOne,
        )));

        /** @var list<Category> $levelTwo */
        $levelTwo = $this->createQueryBuilder('c')
            ->andWhere('c.parent IN (:parents)')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('parents', $parentIds)
            ->setParameter('enabled', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<int, list<array{id: int, name: string, slug: string}>> $childrenByParent */
        $childrenByParent = [];
        foreach ($levelTwo as $child) {
            $parentId = $child->getParent()?->getId();
            $childId = $child->getId();
            if ($parentId === null || $childId === null) {
                continue;
            }

            $childrenByParent[$parentId][] = [
                'id' => $childId,
                'name' => $child->getName(),
                'slug' => $child->getSlug(),
            ];
        }

        $menu = [];
        foreach ($levelOne as $category) {
            $id = $category->getId();
            if ($id === null) {
                continue;
            }

            $menu[] = [
                'id' => $id,
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'children' => $childrenByParent[$id] ?? [],
            ];
        }

        return $menu;
    }

    public function findOneBySlug(string $slug): ?Category
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return $this->findOneBy(['slug' => $slug]);
    }
}
