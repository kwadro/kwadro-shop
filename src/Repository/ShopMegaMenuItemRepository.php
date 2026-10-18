<?php

namespace App\Repository;

use App\Entity\ShopMegaMenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopMegaMenuItem>
 */
class ShopMegaMenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopMegaMenuItem::class);
    }
}
