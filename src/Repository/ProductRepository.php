<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findFeatured(): ?Product
    {
        $featured = $this->createQueryBuilder('p')
            ->select('p.id')
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
            ->andWhere('p.id = :id')
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
            ->setParameter('category', $category)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
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
