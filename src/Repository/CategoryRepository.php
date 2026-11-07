<?php

namespace App\Repository;

use App\Entity\Category;
use App\Service\Menu\MenuCategoryDisplayRules;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Category> */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ProductImportRepository $productImportRepository,
    ) {
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
     * Enabled categories in depth-first tree order (skipping Default):
     * level-1, its children, next level-1, …
     *
     * @return list<Category>
     */
    public function findEnabledInTreeOrder(): array
    {
        /** @var list<Category> $all */
        $all = $this->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')->addSelect('p')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<int, list<Category>> $childrenByParent */
        $childrenByParent = [];
        /** @var list<Category> $enabledNonDefault */
        $enabledNonDefault = [];
        foreach ($all as $category) {
            if (!$category instanceof Category || $category->getId() === null) {
                continue;
            }
            if ($category->isDefault() || !$category->isEnabled()) {
                // Still index Default so its children can be walked.
                if ($category->isDefault()) {
                    $parentId = $category->getParent()?->getId() ?? 0;
                    $childrenByParent[$parentId][] = $category;
                }
                continue;
            }
            $enabledNonDefault[] = $category;
            $parentId = $category->getParent()?->getId() ?? 0;
            $childrenByParent[$parentId][] = $category;
        }

        $default = $this->findDefaultItem();
        $startIds = [];
        if ($default?->getId() !== null) {
            $startIds[] = $default->getId();
        }
        // Also walk true roots (parent null) in case the tree is not under Default.
        $startIds[] = 0;
        $startIds = array_values(array_unique($startIds));

        $ordered = [];
        $seen = [];
        $walk = function (int $parentId) use (&$walk, &$ordered, &$seen, $childrenByParent): void {
            foreach ($childrenByParent[$parentId] ?? [] as $child) {
                $childId = $child->getId();
                if ($childId === null || isset($seen[$childId])) {
                    continue;
                }
                $seen[$childId] = true;
                if (!$child->isDefault() && $child->isEnabled()) {
                    $ordered[] = $child;
                }
                $walk($childId);
            }
        };
        foreach ($startIds as $startId) {
            $walk($startId);
        }

        foreach ($enabledNonDefault as $category) {
            $id = $category->getId();
            if ($id !== null && !isset($seen[$id])) {
                $ordered[] = $category;
            }
        }

        return $ordered;
    }

    /**
     * @return array<int, int> categoryId => product count
     */
    public function countProductsGroupedByCategoryId(
        bool $enabledProductsOnly = false,
        bool $inStockOnly = false,
    ): array {
        if (!$this->getEntityManager()->getConnection()->createSchemaManager()->tablesExist(['shop_product_category'])) {
            return [];
        }

        if ($enabledProductsOnly || $inStockOnly) {
            $where = [];
            if ($enabledProductsOnly) {
                $where[] = 'p.enabled = 1';
            }
            if ($inStockOnly) {
                $where[] = 'p.in_stock = 1';
            }
            $whereSql = $where !== [] ? 'WHERE '.implode(' AND ', $where) : '';

            $raw = $this->getEntityManager()->getConnection()->fetchAllAssociative(<<<SQL
                SELECT pc.category_id AS category_id, COUNT(pc.product_id) AS product_count
                FROM shop_product_category pc
                INNER JOIN shop_product p ON p.id = pc.product_id
                {$whereSql}
                GROUP BY pc.category_id
            SQL);
        } else {
            $raw = $this->getEntityManager()->getConnection()->fetchAllAssociative(<<<'SQL'
                SELECT category_id AS category_id, COUNT(product_id) AS product_count
                FROM shop_product_category
                GROUP BY category_id
            SQL);
        }

        $counts = [];
        foreach ($raw as $row) {
            $counts[(int) $row['category_id']] = (int) $row['product_count'];
        }

        return $counts;
    }

    /**
     * Products that appear on storefront listings when out-of-stock is hidden:
     * enabled + in stock.
     *
     * @return array<int, int> categoryId => product count
     */
    public function countStorefrontVisibleProductsGroupedByCategoryId(): array
    {
        return $this->countProductsGroupedByCategoryId(true, true);
    }

    /**
     * Distinct enabled + in-stock products in a category plus all enabled descendants.
     * A product linked to several categories of the same subtree is counted once.
     *
     * @return array<int, int> categoryId => subtree product count
     */
    public function countEnabledProductsInSubtreeGroupedByCategoryId(): array
    {
        /** @var list<array{id: int|string, parentId: int|string|null}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.id AS id', 'IDENTITY(c.parent) AS parentId')
            ->andWhere('c.enabled = true')
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, int|null> $parentById enabled categories only */
        $parentById = [];
        foreach ($rows as $row) {
            $parentById[(int) $row['id']] = $row['parentId'] !== null && $row['parentId'] !== ''
                ? (int) $row['parentId']
                : null;
        }

        $assignments = $this->getEntityManager()->getConnection()->fetchAllNumeric(<<<'SQL'
            SELECT DISTINCT pc.category_id, pc.product_id
            FROM shop_product_category pc
            INNER JOIN shop_product p ON p.id = pc.product_id
            WHERE p.enabled = 1 AND p.in_stock = 1
        SQL);

        /** @var array<int, array<int, true>> $productsByCategory */
        $productsByCategory = [];
        foreach ($assignments as [$categoryId, $productId]) {
            $categoryId = (int) $categoryId;
            $productId = (int) $productId;
            // Walk up through enabled ancestors; a disabled category cuts its branch off.
            while ($categoryId !== null && \array_key_exists($categoryId, $parentById)) {
                if (isset($productsByCategory[$categoryId][$productId])) {
                    break;
                }
                $productsByCategory[$categoryId][$productId] = true;
                $categoryId = $parentById[$categoryId];
            }
        }

        $counts = [];
        foreach ($parentById as $id => $_) {
            $counts[$id] = \count($productsByCategory[$id] ?? []);
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
     * Up to three-level menu under Default for the shop header.
     *
     * @return list<array{
     *   id: int,
     *   name: string,
     *   slug: string,
     *   children: list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     children: list<array{id: int, name: string, slug: string}>
     *   }>
     * }>
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

        return $this->buildStorefrontMenuTreeFromRoots($levelOne);
    }

    /**
     * Build the header mega-menu from an explicit ordered list of root categories.
     * Children / grandchildren still come from the category tree (auto).
     *
     * @param list<Category> $levelOne
     * @param bool $forceIncludeRoots When true (admin manual mega menu), keep every
     *                                 selected root even if it currently has no visible products.
     *
     * @return list<array{
     *   id: int,
     *   name: string,
     *   slug: string,
     *   children: list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     children: list<array{id: int, name: string, slug: string}>
     *   }>
     * }>
     */
    public function buildStorefrontMenuTreeFromRoots(array $levelOne, bool $forceIncludeRoots = false): array
    {
        $levelOne = array_values(array_filter(
            $levelOne,
            static fn ($category): bool => $category instanceof Category
                && $category->isEnabled()
                && !$category->isDefault()
                && $category->getId() !== null,
        ));
        $rules = MenuCategoryDisplayRules::fromImports(
            $this->productImportRepository->findBy(['active' => true], ['id' => 'ASC']),
        );
        $levelOne = array_values(array_filter(
            $levelOne,
            static fn (Category $category): bool => $rules->isShownInMenu((int) $category->getId()),
        ));
        if ($levelOne === []) {
            return [];
        }

        // Match category pages that hide out-of-stock: only enabled + in-stock products count.
        // A category without its own visible products is hidden, unless it still has a visible child.
        $directCounts = $this->countStorefrontVisibleProductsGroupedByCategoryId();

        $levelOneIds = array_values(array_filter(array_map(
            static fn (Category $category): ?int => $category->getId(),
            $levelOne,
        )));

        /** @var list<Category> $levelTwo */
        $levelTwo = $this->createQueryBuilder('c')
            ->andWhere('c.parent IN (:parents)')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('parents', $levelOneIds)
            ->setParameter('enabled', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $levelTwoIds = array_values(array_filter(array_map(
            static fn (Category $category): ?int => $category->getId(),
            $levelTwo,
        )));

        /** @var list<Category> $levelThree */
        $levelThree = $levelTwoIds === [] ? [] : $this->createQueryBuilder('c')
            ->andWhere('c.parent IN (:parents)')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('parents', $levelTwoIds)
            ->setParameter('enabled', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        [$levelTwoNodes, $levelThreeNodes] = $this->arrangeMenuNodes($levelOneIds, $levelTwo, $levelThree, $rules);

        /** @var array<int, list<array{id: int, name: string, slug: string, og_image: ?string}>> $grandchildrenByParent */
        $grandchildrenByParent = [];
        foreach ($levelThreeNodes as $grandchild) {
            $parentId = $grandchild['parent_id'];
            $grandchildId = $grandchild['category']->getId();
            if ($grandchildId === null) {
                continue;
            }
            // Leaf/child: must have enabled products in this category itself.
            if (($directCounts[$grandchildId] ?? 0) < 1) {
                continue;
            }

            $grandchildrenByParent[$parentId][] = $this->menuNode($grandchild['category']);
        }

        /** @var array<int, list<array{id: int, name: string, slug: string, og_image: ?string, children: list}>> $childrenByParent */
        $childrenByParent = [];
        foreach ($levelTwoNodes as $child) {
            $parentId = $child['parent_id'];
            $childId = $child['category']->getId();
            if ($childId === null) {
                continue;
            }

            $grandchildren = $grandchildrenByParent[$childId] ?? [];
            $ownProducts = $directCounts[$childId] ?? 0;
            // Hide when this category has no products and no non-empty children.
            if ($ownProducts < 1 && $grandchildren === []) {
                continue;
            }

            $childrenByParent[$parentId][] = $this->menuNode($child['category'], $grandchildren);
        }

        $menu = [];
        foreach ($levelOne as $category) {
            $id = $category->getId();
            if ($id === null) {
                continue;
            }

            $children = $childrenByParent[$id] ?? [];
            $ownProducts = $directCounts[$id] ?? 0;
            if (!$forceIncludeRoots && $ownProducts < 1 && $children === []) {
                continue;
            }

            $menu[] = [
                'id' => $id,
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'og_image' => $category->getOgImage(),
                'children' => $children,
            ];
        }

        return $menu;
    }

    /**
     * Place categories in the menu using display rules, without changing stored parents.
     * Pinned categories (top list) come first among siblings; everyone else keeps import position.
     *
     * @param list<int> $levelOneIds
     * @param list<Category> $levelTwo
     * @param list<Category> $levelThree
     *
     * @return array{
     *   0: list<array{parent_id: int, category: Category}>,
     *   1: list<array{parent_id: int, category: Category}>
     * }
     */
    private function arrangeMenuNodes(array $levelOneIds, array $levelTwo, array $levelThree, MenuCategoryDisplayRules $rules): array
    {
        /** @var array<int, Category> $catalog */
        $catalog = [];
        foreach ([...$levelTwo, ...$levelThree] as $category) {
            $id = $category->getId();
            if ($id !== null) {
                $catalog[$id] = $category;
            }
        }

        $missingIds = array_values(array_diff(array_keys($rules->displayParentByCategoryId), array_keys($catalog)));
        if ($missingIds !== []) {
            foreach ($this->findBy(['id' => $missingIds]) as $category) {
                if (!$category instanceof Category || !$category->isEnabled() || $category->isDefault()) {
                    continue;
                }
                $id = $category->getId();
                if ($id !== null) {
                    $catalog[$id] = $category;
                }
            }
        }

        $levelOneSet = array_fill_keys($levelOneIds, true);
        $displayParentOf = function (int $id) use ($catalog, $rules, $levelOneSet): ?int {
            $category = $catalog[$id] ?? null;
            if (!$category instanceof Category) {
                return null;
            }
            $mapped = $rules->displayParentByCategoryId[$id] ?? null;
            if (\is_int($mapped) && $mapped !== $id && (isset($levelOneSet[$mapped]) || isset($catalog[$mapped]))) {
                return $mapped;
            }

            return $category->getParent()?->getId();
        };

        /** @var list<array{parent_id: int, category: Category}> $second */
        $second = [];
        /** @var array<int, true> $secondIds */
        $secondIds = [];
        foreach ($catalog as $id => $category) {
            if (!$rules->isShownInMenu($id)) {
                continue;
            }
            $parentId = $displayParentOf($id);
            if ($parentId === null || !isset($levelOneSet[$parentId])) {
                continue;
            }
            $second[] = ['parent_id' => $parentId, 'category' => $category];
            $secondIds[$id] = true;
        }

        /** @var list<array{parent_id: int, category: Category}> $third */
        $third = [];
        foreach ($catalog as $id => $category) {
            if (isset($secondIds[$id]) || !$rules->isShownInMenu($id)) {
                continue;
            }
            $parentId = $displayParentOf($id);
            if ($parentId === null || !isset($secondIds[$parentId])) {
                continue;
            }
            $third[] = ['parent_id' => $parentId, 'category' => $category];
        }

        $sort = function (array $nodes) use ($rules): array {
            /** @var array<int, list<array{parent_id: int, category: Category}>> $grouped */
            $grouped = [];
            foreach ($nodes as $node) {
                $grouped[$node['parent_id']][] = $node;
            }
            $sorted = [];
            foreach ($grouped as $group) {
                usort($group, function (array $a, array $b) use ($rules): int {
                    return $this->compareMenuSiblings($a['category'], $b['category'], $rules);
                });
                array_push($sorted, ...$group);
            }

            return $sorted;
        };

        return [$sort($second), $sort($third)];
    }

    private function compareMenuSiblings(Category $a, Category $b, MenuCategoryDisplayRules $rules): int
    {
        $rank = array_flip($rules->topCategoryIds);
        $aId = $a->getId();
        $bId = $b->getId();
        $aRank = $aId !== null && isset($rank[$aId]) ? $rank[$aId] : null;
        $bRank = $bId !== null && isset($rank[$bId]) ? $rank[$bId] : null;
        if ($aRank !== null || $bRank !== null) {
            if ($aRank === null) {
                return 1;
            }
            if ($bRank === null) {
                return -1;
            }
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
        }

        $byPosition = $a->getPosition() <=> $b->getPosition();
        if ($byPosition !== 0) {
            return $byPosition;
        }

        return strcmp($a->getName(), $b->getName());
    }

    /**
     * @param list<array{id: int, name: string, slug: string, og_image: ?string}> $children
     *
     * @return array{id: int, name: string, slug: string, og_image: ?string, children: list}
     */
    private function menuNode(Category $category, array $children = []): array
    {
        return [
            'id' => (int) $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'og_image' => $category->getOgImage(),
            'children' => $children,
        ];
    }

    /**
     * Enabled child categories as shown in the storefront, including menu-only moves.
     * The category parent stored by import is not changed.
     *
     * @return list<Category>
     */
    public function findStorefrontDisplayChildren(Category $parent): array
    {
        $parentId = $parent->getId();
        if ($parentId === null) {
            return [];
        }

        $rules = MenuCategoryDisplayRules::fromImports(
            $this->productImportRepository->findBy(['active' => true], ['id' => 'ASC']),
        );

        /** @var array<int, Category> $children */
        $children = [];
        foreach ($parent->getChildren() as $child) {
            if (!$child->isEnabled() || $child->isDefault()) {
                continue;
            }
            $childId = $child->getId();
            if ($childId === null || !$rules->isShownInMenu($childId)) {
                continue;
            }
            $displayParent = $rules->displayParentByCategoryId[$childId] ?? $parentId;
            if ($displayParent !== $parentId) {
                continue;
            }
            $children[$childId] = $child;
        }

        $incomingIds = [];
        foreach ($rules->displayParentByCategoryId as $categoryId => $displayParentId) {
            if ($displayParentId === $parentId && !isset($children[$categoryId])) {
                $incomingIds[] = $categoryId;
            }
        }
        if ($incomingIds !== []) {
            foreach ($this->findBy(['id' => $incomingIds]) as $category) {
                if (!$category instanceof Category || !$category->isEnabled() || $category->isDefault()) {
                    continue;
                }
                $incomingId = $category->getId();
                if ($incomingId === null || !$rules->isShownInMenu($incomingId)) {
                    continue;
                }
                $id = $category->getId();
                if ($id !== null) {
                    $children[$id] = $category;
                }
            }
        }

        $list = array_values($children);
        usort($list, fn (Category $a, Category $b): int => $this->compareMenuSiblings($a, $b, $rules));

        return $list;
    }

    public function findOneBySlug(string $slug): ?Category
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<array{slug: string, updatedAt: \DateTimeImmutable|null}>
     */
    public function findPublishedSitemapEntries(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.slug AS slug', 'c.updated_at AS updatedAt')
            ->andWhere('c.enabled = true')
            ->andWhere('c.slug != :defaultSlug')
            ->andWhere('c.name != :defaultName')
            ->setParameter('defaultSlug', Category::DEFAULT_SLUG)
            ->setParameter('defaultName', Category::DEFAULT_NAME)
            ->andWhere("c.slug != ''")
            ->orderBy('c.slug', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => \is_string($row['slug'] ?? null) && $row['slug'] !== '',
        ));
    }
}
