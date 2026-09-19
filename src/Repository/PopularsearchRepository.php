<?php
namespace App\Repository;
use App\Entity\Popularsearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Popularsearch>
*/
class PopularsearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Popularsearch::class);
    }
       public function  findAllBySiteAndLocale($site, $locale){
           return $this->createQueryBuilder('s')
               ->andWhere('s.locale = :locale')
               ->andWhere('s.site = :site')
               ->setParameter('site', $site)
               ->setParameter('locale', $locale)
               ->setMaxResults(8)
               ->getQuery()
               ->getResult();
       }
}
