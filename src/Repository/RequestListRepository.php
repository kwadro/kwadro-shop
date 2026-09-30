<?php

namespace App\Repository;

use App\Entity\RequestList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RequestList> */
class RequestListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequestList::class);
    }

    public function countUniqueIpsToday(?\DateTimeZone $timezone = null): int
    {
        $timezone ??= new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $from = (new \DateTimeImmutable('today', $timezone));
        $to = $from->modify('+1 day');

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.ip)')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->andWhere('r.ip IS NOT NULL')
            ->andWhere("r.ip <> ''")
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countToday(?\DateTimeZone $timezone = null): int
    {
        $timezone ??= new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $from = (new \DateTimeImmutable('today', $timezone));
        $to = $from->modify('+1 day');

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
