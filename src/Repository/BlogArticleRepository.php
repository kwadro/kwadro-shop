<?php

namespace App\Repository;

use App\Entity\BlogArticle;
use App\Entity\Locale;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BlogArticle> */
class BlogArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogArticle::class);
    }

    /** @return list<BlogArticle> */
    public function findPublishedBySiteAndLocale(Site $site, Locale $locale): array
    {
        $now = new \DateTimeImmutable();

        /** @var list<BlogArticle> $items */
        $items = $this->createQueryBuilder('a')
            ->andWhere('a.site = :site')
            ->andWhere('a.locale = :locale')
            ->andWhere('a.enabled = true')
            ->andWhere('a.publishedAt IS NULL OR a.publishedAt <= :now')
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function findOnePublishedBySiteLocaleAndSlug(Site $site, Locale $locale, string $slug): ?BlogArticle
    {
        $now = new \DateTimeImmutable();

        /** @var BlogArticle|null $article */
        $article = $this->createQueryBuilder('a')
            ->andWhere('a.site = :site')
            ->andWhere('a.locale = :locale')
            ->andWhere('a.slug = :slug')
            ->andWhere('a.enabled = true')
            ->andWhere('a.publishedAt IS NULL OR a.publishedAt <= :now')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $article;
    }

    public function slugExists(Site $site, Locale $locale, string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.site = :site')
            ->andWhere('a.locale = :locale')
            ->andWhere('a.slug = :slug')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
