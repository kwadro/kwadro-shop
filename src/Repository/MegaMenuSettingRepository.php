<?php
namespace App\Repository;
use App\Entity\MegaMenuSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MegaMenuSetting>
*/
class MegaMenuSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MegaMenuSetting::class);
    }

    public function findBySiteAndLocale($site, $locale)
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.translations', 't')
            ->addSelect('t')
            ->leftJoin('t.megamenutype', 'mt')
            ->addSelect('mt')
            ->where('s.site = :site')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.status = :status')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('status', 'Yes')
            ->addOrderBy('CASE WHEN t.position IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function findBySiteAndLocaleAndSlug(string $slug, $site, $locale)
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.translations', 't')
            ->addSelect('t')
            ->leftJoin('t.megamenutype', 'mt')
            ->addSelect('mt')
            ->where('s.site = :site')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.status = :status')
            ->andWhere('t.url = :slug')
            ->setParameter('slug', $slug)
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('status', 'Yes')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
