<?php

namespace App\Repository;

use App\Entity\ProductImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductImport> */
class ProductImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImport::class);
    }

    public function findOneByCode(string $code): ?ProductImport
    {
        return $this->findOneBy(['code' => trim(mb_strtolower($code))]);
    }
}
