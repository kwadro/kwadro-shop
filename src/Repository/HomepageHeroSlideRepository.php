<?php

namespace App\Repository;

use App\Entity\HomepageHeroSlide;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomepageHeroSlide>
 */
class HomepageHeroSlideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepageHeroSlide::class);
    }
}
