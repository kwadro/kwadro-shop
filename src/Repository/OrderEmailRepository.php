<?php

namespace App\Repository;

use App\Entity\OrderEmail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrderEmail> */
class OrderEmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderEmail::class);
    }

    public function findOneByCode(string $code): ?OrderEmail
    {
        $code = trim(strtolower($code));
        if ($code === '') {
            return null;
        }

        return $this->findOneBy(['code' => $code]);
    }
}
