<?php
namespace App\Repository;
use App\Entity\FooterSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FooterSetting>
*/
class FooterSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FooterSetting::class);
    }

    public function findOneBySiteAndLocale($site, $locale)
    {
    return $this->createQueryBuilder('s')
        ->innerJoin('s.site', 'site')
        ->innerJoin('s.translations', 't')
        ->addSelect('t')
        ->where('site = :site')
        ->andWhere('t.locale = :locale')
        ->setParameter('site', $site)
        ->setParameter('locale', $locale)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    }
}
