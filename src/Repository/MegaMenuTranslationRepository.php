<?php
namespace App\Repository;
use App\Entity\Locale;
use App\Entity\MegaMenuTranslation;
use App\Entity\Site;
use App\Routing\ShopRoutes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MegaMenuTranslation>
*/
class MegaMenuTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MegaMenuTranslation::class);
    }

    public function findOnePublishedBySiteLocaleAndSlug(Site $site, Locale $locale, string $slug): ?MegaMenuTranslation
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.megamenusetting', 's')
            ->innerJoin('t.megamenutype', 'type')
            ->where('s.site = :site')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.url = :slug')
            ->andWhere('t.status = :status')
            ->andWhere('type.name IN  (:types)')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug)
            ->setParameter('status', 'Yes')
            ->setParameter('types', ShopRoutes::INDEXABLE_MENU_TYPES)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<array{slug: string, locale: string, updatedAt: \DateTimeImmutable|null}>
     */
    public function findPublishedSitemapEntries(Site $site): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.url AS slug', 'l.code AS locale', 't.updated_at AS updatedAt')
            ->innerJoin('t.megamenusetting', 's')
            ->innerJoin('t.locale', 'l')
            ->innerJoin('t.megamenutype', 'type')
            ->where('s.site = :site')
            ->andWhere('t.status = :status')
            ->andWhere('type.name IN (:types)')
            ->andWhere('t.url IS NOT NULL')
            ->andWhere('t.url != :empty')
            ->andWhere('t.url NOT IN (:excludedUrls)')
            ->setParameter('site', $site)
            ->setParameter('status', 'Yes')
            ->setParameter('types', ShopRoutes::INDEXABLE_MENU_TYPES)
            ->setParameter('empty', '')
            ->setParameter('excludedUrls', ShopRoutes::SITEMAP_EXCLUDED_URLS)
            ->orderBy('t.url', 'ASC')
            ->addOrderBy('l.code', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => \is_string($row['slug'] ?? null)
                && $row['slug'] !== ''
                && \is_string($row['locale'] ?? null),
        ));
    }
}
