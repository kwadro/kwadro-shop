<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
class ProductRepository extends ServiceEntityRepository
{
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
     * @return list<Product>
     */
    public function findByCategoryWithOffers(Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->andWhere('c = :category')
            ->andWhere('p.enabled = true')
            ->setParameter('category', $category)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Product>
     */
    public function findBySupplierWithOffers(Supplier $supplier): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.offers', 'o')
            ->addSelect('o')
            ->innerJoin('o.supplier', 's')
            ->addSelect('s')
            ->leftJoin('p.categories', 'c')
            ->addSelect('c')
            ->andWhere('s = :supplier')
            ->andWhere('p.enabled = true')
            ->setParameter('supplier', $supplier)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public const FILTER_ATTRIBUTES = ['brand', 'color', 'type', 'model'];

    /**
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null,
     *   filters?: array<string, list<string>>
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
            ->groupBy('p.id')
            ->addGroupBy('p.name')
            ->orderBy('p.name', 'ASC')
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
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Facet values with product counts within the current catalog scope.
     * Counts respect other selected filters, but ignore the filter of the facet attribute itself.
     *
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null,
     *   filters?: array<string, list<string>>
     * } $criteria
     * @return array<string, list<array{value: string, count: int}>>
     */
    public function findAttributeFacets(array $criteria): array
    {
        $filters = $criteria['filters'] ?? [];
        $facets = [];

        foreach (self::FILTER_ATTRIBUTES as $attribute) {
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

            $options = [];
            foreach ($rows as $row) {
                $value = trim((string) ($row['value'] ?? ''));
                $count = (int) ($row['cnt'] ?? 0);
                if ($value === '' || $count < 1) {
                    continue;
                }
                $options[] = [
                    'value' => $value,
                    'count' => $count,
                ];
            }

            $facets[$attribute] = $options;
        }

        return $facets;
    }

    /**
     * @param array{
     *   category?: Category|null,
     *   supplier?: Supplier|null,
     *   query?: string|null,
     *   filters?: array<string, list<string>>
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

        $query = trim((string) ($criteria['query'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :searchQuery')
                ->setParameter('searchQuery', '%'.mb_strtolower($query).'%');
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

        return $qb;
    }

    /**
     * Products assigned to at least one public (enabled, non-default) category.
     *
     * @return list<array{slug: string, updatedAt: \DateTimeImmutable|null}>
     */
    public function findPublishedSitemapEntries(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.slug AS slug', 'p.updated_at AS updatedAt')
            ->innerJoin('p.categories', 'c')
            ->andWhere('p.enabled = true')
            ->andWhere('c.enabled = true')
            ->andWhere('c.slug != :defaultSlug')
            ->andWhere('c.name != :defaultName')
            ->andWhere("p.slug != ''")
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
