<?php

namespace App\Repository;

use App\Entity\HomepagePromoStripItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomepagePromoStripItem>
 */
class HomepagePromoStripItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepagePromoStripItem::class);
    }
}
