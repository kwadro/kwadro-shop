<?php

namespace App\Repository;

use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Supplier> */
class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    public function findOneBySlug(string $slug): ?Supplier
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return $this->findOneBy(['slug' => $slug]);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('s.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return list<array{slug: string, updatedAt: \DateTimeImmutable|null}>
     */
    public function findPublishedSitemapEntries(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.slug AS slug', 's.updated_at AS updatedAt')
            ->andWhere("s.slug != ''")
            ->orderBy('s.slug', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => \is_string($row['slug'] ?? null) && $row['slug'] !== '',
        ));
    }
}
