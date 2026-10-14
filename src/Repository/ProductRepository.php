<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductOffer;
use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
class ProductRepository extends ServiceEntityRepository
{
    private const SEARCH_CACHE_LIMIT = 20;

    /** @var array<string, list<int>> search key => matching product ids (per request / worker) */
    private array $searchIdsCache = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findFeatured(?int $featureId = null): ?Product
    {
        if ($featureId !== null && $featureId > 0) {
            $product = $this->findOneWithOffers($featureId);
            if ($product !== null) {
                return $product;
            }
        }

        $featured = $this->createQueryBuilder('p')
            ->select('p.id')
            ->andWhere('p.enabled = true')
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($featured === null) {
            return null;
        }

        return $this->findOneWithOffers((int) $featured['id']);
    }

    public function findOneWithOffers(int $id): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->leftJoin('p.categories', 'c')
            ->addSelect('c')
            ->leftJoin('p.attributeValues', 'av')
            ->addSelect('av')
            ->leftJoin('av.attribute', 'attr')
            ->addSelect('attr')
            ->leftJoin('attr.group', 'attrGroup')
            ->addSelect('attrGroup')
            ->andWhere('p.id = :id')
            ->andWhere('p.enabled = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneBySlugWithOffers(string $slug): ?Product
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->leftJoin('p.categories', 'c')
            ->addSelect('c')
            ->leftJoin('p.attributeValues', 'av')
            ->addSelect('av')
            ->leftJoin('av.attribute', 'attr')
            ->addSelect('attr')
            ->leftJoin('attr.group', 'attrGroup')
            ->addSelect('attrGroup')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.enabled = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Newest in-stock products for the homepage showcase.
     *
     * When $onlyIds is given, only those products are considered (manual selection).
     *
     * @param list<int>|null $onlyIds
     *
     * @return list<Product>
     */
    public function findHomepageProducts(int $limit = 5, ?array $onlyIds = null): array
    {
        $limit = max(1, min(24, $limit));

        $qb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->andWhere('p.enabled = true')
            ->andWhere('p.in_stock = true')
            ->andWhere('EXISTS (SELECT 1 FROM App\Entity\ProductOffer o_home WHERE o_home.product = p AND o_home.qty > 0)')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit);

        if ($onlyIds !== null) {
            if ($onlyIds === []) {
                return [];
            }
            $qb->andWhere('p.id IN (:onlyIds)')->setParameter('onlyIds', $onlyIds);
        }

        $ids = $qb->getQuery()->getSingleColumnResult();

        if ($ids === []) {
            return [];
        }

        $items = $this->createQueryBuilder('p')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return $this->orderProductsByIds($items, $ids);
    }

    /**
     * @return list<Product>
     */
    public function findByCategoryWithOffers(Category $category): array
    {
        $products = $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->andWhere('c = :category')
            ->andWhere('p.enabled = true')
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();

        if (!$category->isShowOutOfStock()) {
            $products = array_values(array_filter(
                $products,
                static fn (Product $product): bool => $product->isAvailableForSale(),
            ));
        }

        return $this->sortProductsForListing($products);
    }

    /**
     * @return list<Product>
     */
    public function findBySupplierWithOffers(Supplier $supplier): array
    {
        $products = $this->createQueryBuilder('p')
            ->innerJoin('p.offers', 'o')
            ->addSelect('o')
            ->innerJoin('o.supplier', 's')
            ->addSelect('s')
            ->leftJoin('p.categories', 'c')
            ->addSelect('c')
            ->andWhere('s = :supplier')
            ->andWhere('p.enabled = true')
            ->setParameter('supplier', $supplier)
            ->getQuery()
            ->getResult();

        return $this->sortProductsForListing($products);
    }

    public const FILTER_ATTRIBUTES = ['brand', 'color', 'type', 'model'];

    /**
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null,
     *   filters?: array<string, list<string>>,
     *   searchFields?: list<string>,
     *   inStockOnly?: bool,
     *   filterAttributes?: list<ProductAttribute>
     * } $criteria
     * @return array{items: list<Product>, total: int}
     */
    public function findCatalogPage(array $criteria, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $countQb = $this->createCatalogQueryBuilder($criteria);
        $total = (int) $countQb
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        $ids = $this->createCatalogQueryBuilder($criteria)
            ->select('p.id')
            ->leftJoin('p.offers', 'o_sort')
            ->addSelect('CASE WHEN p.in_stock = true AND SUM(CASE WHEN o_sort.qty > 0 THEN 1 ELSE 0 END) > 0 THEN 0 ELSE 1 END AS HIDDEN listing_stock_rank')
            ->addSelect('CASE WHEN MIN(o_sort.price) IS NULL THEN 999999999 ELSE MIN(o_sort.price) END AS HIDDEN listing_min_price')
            ->groupBy('p.id')
            ->addGroupBy('p.in_stock')
            ->orderBy('listing_stock_rank', 'ASC')
            ->addOrderBy('listing_min_price', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getSingleColumnResult();

        if ($ids === []) {
            return ['items' => [], 'total' => $total];
        }

        $items = $this->createQueryBuilder('p')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->leftJoin('p.categories', 'c')
            ->addSelect('c')
            ->leftJoin('p.attributeValues', 'av')
            ->addSelect('av')
            ->leftJoin('av.attribute', 'attr')
            ->addSelect('attr')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return ['items' => $this->orderProductsByIds($items, $ids), 'total' => $total];
    }

    /**
     * Facet values with product counts within the current catalog scope.
     * Counts respect other selected filters, but ignore the filter of the facet attribute itself.
     *
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null,
     *   filters?: array<string, list<string>>,
     *   searchFields?: list<string>,
     *   inStockOnly?: bool,
     *   filterAttributes?: list<ProductAttribute>
     * } $criteria
     * @return array<string, list<array{value: string, count: int}>>
     */
    public function findAttributeFacets(array $criteria): array
    {
        $filters = $criteria['filters'] ?? [];
        $facets = [];

        $builtInKeys = isset($criteria['facetKeys']) && \is_array($criteria['facetKeys'])
            ? array_values(array_intersect(self::FILTER_ATTRIBUTES, $criteria['facetKeys']))
            : self::FILTER_ATTRIBUTES;

        foreach ($builtInKeys as $attribute) {
            $facetCriteria = $criteria;
            $facetCriteria['filters'] = $filters;
            unset($facetCriteria['filters'][$attribute]);

            $rows = $this->createCatalogQueryBuilder($facetCriteria)
                ->select(sprintf('p.%s AS value', $attribute), 'COUNT(DISTINCT p.id) AS cnt')
                ->andWhere(sprintf('p.%s IS NOT NULL', $attribute))
                ->andWhere(sprintf("p.%s != ''", $attribute))
                ->groupBy(sprintf('p.%s', $attribute))
                ->orderBy('value', 'ASC')
                ->getQuery()
                ->getArrayResult();

            $facets[$attribute] = $this->mapFacetRows($rows);
        }

        // Dynamic attributes: one grouped query for all attributes without own selection,
        // plus one query per selected filter key (its own selection must be ignored).
        $attributeIdsByKey = [];
        foreach ($this->groupAttributesByFilterKey($criteria['filterAttributes'] ?? [], true) as $filterKey => $attributes) {
            $attributeIdsByKey[$filterKey] = array_map(static fn (ProductAttribute $a): int => (int) $a->getId(), $attributes);
            $facets[$filterKey] = [];
        }

        $unselectedIds = [];
        foreach ($attributeIdsByKey as $filterKey => $attributeIds) {
            if (($filters[$filterKey] ?? []) === []) {
                array_push($unselectedIds, ...$attributeIds);
                continue;
            }

            $facetCriteria = $criteria;
            $facetCriteria['filters'] = $filters;
            unset($facetCriteria['filters'][$filterKey]);
            $rows = $this->fetchAttributeFacetRows($this->findScopeProductIds($facetCriteria), $attributeIds);
            $facets[$filterKey] = $this->mapFacetRows(array_merge(...array_values($rows ?: [[]])));
        }

        if ($unselectedIds !== []) {
            $rowsByAttribute = $this->fetchAttributeFacetRows($this->findScopeProductIds($criteria), $unselectedIds);
            foreach ($attributeIdsByKey as $filterKey => $attributeIds) {
                if (($filters[$filterKey] ?? []) !== []) {
                    continue;
                }
                $rows = [];
                foreach ($attributeIds as $attributeId) {
                    array_push($rows, ...($rowsByAttribute[$attributeId] ?? []));
                }
                $facets[$filterKey] = $this->mapFacetRows($rows);
            }
        }

        return $facets;
    }

    /**
     * Product ids matching the free-text search. Computed once per query string
     * so count, page and facet queries reuse it instead of re-running LIKE scans.
     * Columns use case-insensitive collations, so LOWER() is not needed.
     *
     * @param list<string>|null $searchFields
     * @return list<int>
     */
    public function findIdsMatchingSearch(string $query, ?array $searchFields = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $fields = $this->normalizeSearchFields($searchFields);
        $cacheKey = implode(',', $fields)."\0".mb_strtolower($query);
        if (isset($this->searchIdsCache[$cacheKey])) {
            return $this->searchIdsCache[$cacheKey];
        }

        $productConditions = implode(' OR ', array_map(
            static fn (string $field): string => sprintf('p.%s LIKE :q', $field),
            $fields,
        ));

        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT p.id FROM shop_product p WHERE p.enabled = 1 AND ({$productConditions})
             UNION
             SELECT av.product_id
             FROM shop_product_attribute_value av
             INNER JOIN shop_product_attribute a ON a.id = av.attribute_id AND a.use_in_search = 1
             WHERE av.value_varchar LIKE :q OR av.value_text LIKE :q",
            ['q' => '%'.addcslashes($query, '%_\\').'%'],
        );

        if (\count($this->searchIdsCache) >= self::SEARCH_CACHE_LIMIT) {
            $this->searchIdsCache = [];
        }

        return $this->searchIdsCache[$cacheKey] = array_map('intval', $ids);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return list<int>
     */
    private function findScopeProductIds(array $criteria): array
    {
        return array_map('intval', $this->createCatalogQueryBuilder($criteria)
            ->select('DISTINCT p.id')
            ->getQuery()
            ->getSingleColumnResult());
    }

    /**
     * @param list<int> $productIds
     * @param list<int> $attributeIds
     * @return array<int, list<array{value: string, cnt: int}>> attributeId => rows
     */
    private function fetchAttributeFacetRows(array $productIds, array $attributeIds): array
    {
        if ($productIds === [] || $attributeIds === []) {
            return [];
        }

        $raw = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT av.attribute_id, av.value_varchar AS value, COUNT(DISTINCT av.product_id) AS cnt
             FROM shop_product_attribute_value av
             WHERE av.product_id IN (:productIds)
               AND av.attribute_id IN (:attributeIds)
               AND av.value_varchar IS NOT NULL
               AND av.value_varchar <> ''
             GROUP BY av.attribute_id, av.value_varchar
             ORDER BY av.value_varchar ASC",
            ['productIds' => $productIds, 'attributeIds' => array_values(array_unique($attributeIds))],
            ['productIds' => ArrayParameterType::INTEGER, 'attributeIds' => ArrayParameterType::INTEGER],
        );

        $rows = [];
        foreach ($raw as $row) {
            $rows[(int) $row['attribute_id']][] = ['value' => (string) $row['value'], 'cnt' => (int) $row['cnt']];
        }

        return $rows;
    }

    /**
     * Attributes from different groups may share a slug (same filter key) — treat them as one filter.
     *
     * @param list<mixed> $attributes
     * @return array<string, list<ProductAttribute>>
     */
    private function groupAttributesByFilterKey(array $attributes, bool $filterableOnly): array
    {
        $grouped = [];
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof ProductAttribute || $attribute->getId() === null) {
                continue;
            }
            if ($filterableOnly && !$attribute->isShowInFilter()) {
                continue;
            }
            $grouped[$attribute->getFilterKey()][] = $attribute;
        }

        return $grouped;
    }

    /**
     * @param list<array{value?: mixed, cnt?: mixed}> $rows
     * @return list<array{value: string, count: int}>
     */
    private function mapFacetRows(array $rows): array
    {
        /** @var array<string, int> $counts rows from several attributes may repeat a value */
        $counts = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            $count = (int) ($row['cnt'] ?? 0);
            if ($value === '' || $count < 1) {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + $count;
        }

        uksort($counts, static fn (string|int $a, string|int $b): int => strnatcasecmp((string) $a, (string) $b));

        $options = [];
        foreach ($counts as $value => $count) {
            $options[] = [
                'value' => (string) $value,
                'count' => $count,
            ];
        }

        return $options;
    }

    /**
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null,
     *   filters?: array<string, list<string>>,
     *   searchFields?: list<string>,
     *   inStockOnly?: bool,
     *   filterAttributes?: list<ProductAttribute>
     * } $criteria
     */
    private function createCatalogQueryBuilder(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.enabled = true');

        if (($criteria['category'] ?? null) instanceof Category) {
            $qb->innerJoin('p.categories', 'c_scope')
                ->andWhere('c_scope = :category')
                ->setParameter('category', $criteria['category']);
        }

        if (($criteria['supplier'] ?? null) instanceof Supplier) {
            $qb->innerJoin('p.offers', 'o_scope')
                ->innerJoin('o_scope.supplier', 's_scope')
                ->andWhere('s_scope = :supplier')
                ->setParameter('supplier', $criteria['supplier']);
        }

        if (!empty($criteria['inStockOnly'])) {
            $qb->andWhere('p.in_stock = true')
                ->andWhere('EXISTS (SELECT 1 FROM App\Entity\ProductOffer o_avail WHERE o_avail.product = p AND o_avail.qty > 0)');
        }

        $query = trim((string) ($criteria['query'] ?? ''));
        if ($query !== '') {
            $searchIds = $this->findIdsMatchingSearch($query, $criteria['searchFields'] ?? null);
            if ($searchIds === []) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('p.id IN (:searchIds)')
                    ->setParameter('searchIds', $searchIds);
            }
        }

        $filters = $criteria['filters'] ?? [];
        foreach (self::FILTER_ATTRIBUTES as $attribute) {
            $values = $filters[$attribute] ?? [];
            if ($values === []) {
                continue;
            }
            $qb->andWhere(sprintf('p.%s IN (:filter_%s)', $attribute, $attribute))
                ->setParameter('filter_'.$attribute, $values);
        }

        $index = 0;
        foreach ($this->groupAttributesByFilterKey($criteria['filterAttributes'] ?? [], false) as $filterKey => $attributes) {
            $values = $filters[$filterKey] ?? [];
            if ($values === []) {
                continue;
            }
            ++$index;
            $qb->andWhere(sprintf(
                'EXISTS (SELECT 1 FROM App\Entity\ProductAttributeValue av_f%1$d
                    WHERE av_f%1$d.product = p
                      AND av_f%1$d.attribute IN (:filterAttrs%1$d)
                      AND av_f%1$d.value_varchar IN (:filterVals%1$d))',
                $index,
            ))
                ->setParameter('filterAttrs'.$index, $attributes)
                ->setParameter('filterVals'.$index, $values);
        }

        return $qb;
    }

    /**
     * @param list<string>|null $fields
     * @return list<string>
     */
    private function normalizeSearchFields(?array $fields): array
    {
        $allowed = ['name', 'model', 'sku', 'brand'];
        if (!\is_array($fields) || $fields === []) {
            return ['name', 'model', 'sku'];
        }

        $normalized = array_values(array_intersect($fields, $allowed));

        return $normalized !== [] ? $normalized : ['name', 'model', 'sku'];
    }

    /**
     * In stock first, then by lowest price ascending.
     *
     * @param list<Product> $products
     * @return list<Product>
     */
    private function sortProductsForListing(array $products): array
    {
        usort($products, static function (Product $a, Product $b): int {
            $aRank = $a->isAvailableForSale() ? 0 : 1;
            $bRank = $b->isAvailableForSale() ? 0 : 1;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            $aPrice = $a->getLowestPrice();
            $bPrice = $b->getLowestPrice();
            if ($aPrice === null && $bPrice === null) {
                return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
            }
            if ($aPrice === null) {
                return 1;
            }
            if ($bPrice === null) {
                return -1;
            }

            $cmp = $aPrice <=> $bPrice;

            return $cmp !== 0 ? $cmp : (($a->getId() ?? 0) <=> ($b->getId() ?? 0));
        });

        return array_values($products);
    }

    /**
     * @param list<Product> $products
     * @param list<int|string> $ids
     * @return list<Product>
     */
    private function orderProductsByIds(array $products, array $ids): array
    {
        $byId = [];
        foreach ($products as $product) {
            $id = $product->getId();
            if ($id !== null) {
                $byId[$id] = $product;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * Admin search by SKU / name / model / brand.
     *
     * @return list<Product>
     */
    public function searchForAdmin(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults(max(1, min(50, $limit)));

        $like = '%'.mb_strtolower($query).'%';
        $qb->andWhere($qb->expr()->orX(
            'LOWER(p.sku) LIKE :q',
            'LOWER(p.name) LIKE :q',
            'LOWER(COALESCE(p.model, \'\')) LIKE :q',
            'LOWER(COALESCE(p.brand, \'\')) LIKE :q',
        ))->setParameter('q', $like);

        return $qb->getQuery()->getResult();
    }

    /**
     * Products assigned to at least one public (enabled, non-default) category.
     * Products offered only by suppliers hidden from the sitemap are skipped;
     * products without offers or with offers from a visible supplier stay listed.
     *
     * @return list<array{slug: string, updatedAt: \DateTimeImmutable|null}>
     */
    public function findPublishedSitemapEntries(): array
    {
        $hiddenOffers = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(ProductOffer::class, 'ho')
            ->innerJoin('ho.supplier', 'hs')
            ->andWhere('ho.product = p')
            ->andWhere('hs.show_products_in_sitemap = false');
        $visibleOffers = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(ProductOffer::class, 'vo')
            ->leftJoin('vo.supplier', 'vs')
            ->andWhere('vo.product = p')
            ->andWhere('vs.id IS NULL OR vs.show_products_in_sitemap = true');

        $rows = $this->createQueryBuilder('p')
            ->select('p.slug AS slug', 'p.updated_at AS updatedAt')
            ->innerJoin('p.categories', 'c')
            ->andWhere('p.enabled = true')
            ->andWhere('c.enabled = true')
            ->andWhere('c.slug != :defaultSlug')
            ->andWhere('c.name != :defaultName')
            ->andWhere("p.slug != ''")
            ->andWhere(\sprintf('NOT EXISTS(%s) OR EXISTS(%s)', $hiddenOffers->getDQL(), $visibleOffers->getDQL()))
            ->setParameter('defaultSlug', Category::DEFAULT_SLUG)
            ->setParameter('defaultName', Category::DEFAULT_NAME)
            ->groupBy('p.id')
            ->addGroupBy('p.slug')
            ->addGroupBy('p.updated_at')
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => \is_string($row['slug'] ?? null) && $row['slug'] !== '',
        ));
    }
}
