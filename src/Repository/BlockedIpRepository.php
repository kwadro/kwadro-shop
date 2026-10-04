<?php

namespace App\Repository;

use App\Entity\BlockedIp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BlockedIp> */
class BlockedIpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedIp::class);
    }

    public function isIpBlocked(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }

        $count = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.ip = :ip')
            ->andWhere('b.isActive = true')
            ->setParameter('ip', $ip)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
