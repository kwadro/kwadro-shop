<?php

namespace App\Repository;

use App\Entity\BlogCategory;
use App\Entity\Locale;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BlogCategory> */
class BlogCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogCategory::class);
    }

    /** @return list<BlogCategory> */
    public function findEnabledBySiteAndLocale(Site $site, Locale $locale): array
    {
        /** @var list<BlogCategory> $items */
        $items = $this->createQueryBuilder('c')
            ->andWhere('c.site = :site')
            ->andWhere('c.locale = :locale')
            ->andWhere('c.enabled = true')
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function findOneEnabledBySiteLocaleAndSlug(Site $site, Locale $locale, string $slug): ?BlogCategory
    {
        /** @var BlogCategory|null $category */
        $category = $this->createQueryBuilder('c')
            ->andWhere('c.site = :site')
            ->andWhere('c.locale = :locale')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.enabled = true')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $category;
    }

    public function slugExists(Site $site, Locale $locale, string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.site = :site')
            ->andWhere('c.locale = :locale')
            ->andWhere('c.slug = :slug')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
