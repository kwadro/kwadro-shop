<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findFeatured(): ?Product
    {
        $featured = $this->createQueryBuilder('p')
            ->select('p.id')
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($featured === null) {
            return null;
        }

        return $this->findOneWithOffers((int) $featured['id']);
    }

    public function findOneWithOffers(int $id): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
