<?php

namespace App\Repository;

use App\Entity\BlogArticle;
use App\Entity\BlogCategory;
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
        return $this->publishedQuery($site, $locale)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<BlogArticle> */
    public function findPublishedByCategory(BlogCategory $category): array
    {
        $site = $category->getSite();
        $locale = $category->getLocale();
        if ($site === null || $locale === null) {
            return [];
        }

        /** @var list<BlogArticle> $items */
        $items = $this->publishedQuery($site, $locale)
            ->innerJoin('a.categories', 'c')
            ->andWhere('c = :category')
            ->setParameter('category', $category)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /** @return list<BlogArticle> */
    public function searchPublished(Site $site, Locale $locale, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%'.mb_strtolower($query).'%';

        /** @var list<BlogArticle> $items */
        $items = $this->publishedQuery($site, $locale)
            ->andWhere('LOWER(a.title) LIKE :q OR LOWER(COALESCE(a.tags, \'\')) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /** @return list<BlogArticle> */
    public function findPublishedByYearMonth(Site $site, Locale $locale, int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $end = $start->modify('first day of next month');

        /** @var list<BlogArticle> $items */
        $items = $this->publishedQuery($site, $locale)
            ->andWhere('a.publishedAt >= :start')
            ->andWhere('a.publishedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /**
     * @return list<array{year: int, month: int, count: int}>
     */
    public function findPublishedMonthBuckets(Site $site, Locale $locale): array
    {
        $articles = $this->findPublishedBySiteAndLocale($site, $locale);
        $counts = [];
        foreach ($articles as $article) {
            $publishedAt = $article->getPublishedAt();
            if ($publishedAt === null) {
                continue;
            }
            $key = $publishedAt->format('Y-n');
            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'year' => (int) $publishedAt->format('Y'),
                    'month' => (int) $publishedAt->format('n'),
                    'count' => 0,
                ];
            }
            ++$counts[$key]['count'];
        }

        return array_values($counts);
    }

    public function findOnePublishedBySiteLocaleAndSlug(Site $site, Locale $locale, string $slug): ?BlogArticle
    {
        /** @var BlogArticle|null $article */
        $article = $this->publishedQuery($site, $locale)
            ->andWhere('a.slug = :slug')
            ->setParameter('slug', $slug)
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

    private function publishedQuery(Site $site, Locale $locale): \Doctrine\ORM\QueryBuilder
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Kyiv'));

        return $this->createQueryBuilder('a')
            ->andWhere('a.site = :site')
            ->andWhere('a.locale = :locale')
            ->andWhere('a.enabled = true')
            ->andWhere('a.publishedAt IS NOT NULL')
            ->andWhere('a.publishedAt <= :now')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('now', $now);
    }
}
