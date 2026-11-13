<?php

namespace App\Repository;

use App\Entity\ProductAttributeGroupItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductAttributeGroupItem> */
class ProductAttributeGroupItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductAttributeGroupItem::class);
    }
}
