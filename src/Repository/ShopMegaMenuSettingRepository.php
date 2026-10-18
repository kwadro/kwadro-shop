<?php

namespace App\Repository;

use App\Entity\Locale;
use App\Entity\ShopMegaMenuSetting;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopMegaMenuSetting>
 */
class ShopMegaMenuSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopMegaMenuSetting::class);
    }

    public function findOneBySiteAndLocale(Site $site, Locale $locale): ?ShopMegaMenuSetting
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.items', 'i')->addSelect('i')
            ->leftJoin('i.category', 'c')->addSelect('c')
            ->andWhere('s.site = :site')
            ->andWhere('s.locale = :locale')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->addOrderBy('i.position', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
