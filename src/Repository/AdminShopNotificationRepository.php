<?php

namespace App\Repository;

use App\Entity\AdminShopNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AdminShopNotification> */
class AdminShopNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminShopNotification::class);
    }

    /** @return list<AdminShopNotification> */
    public function findUnnotified(int $limit = 20): array
    {
        /** @var list<AdminShopNotification> $items */
        $items = $this->createQueryBuilder('n')
            ->andWhere('n.isNotified = false')
            ->orderBy('n.created_at', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function findPendingFor(string $channel, int $entityId): ?AdminShopNotification
    {
        /** @var AdminShopNotification|null $item */
        $item = $this->createQueryBuilder('n')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.entityId = :entityId')
            ->andWhere('n.isNotified = false')
            ->setParameter('channel', $channel)
            ->setParameter('entityId', $entityId)
            ->orderBy('n.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $item;
    }

    public function existsFor(string $channel, int $entityId): bool
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.entityId = :entityId')
            ->setParameter('channel', $channel)
            ->setParameter('entityId', $entityId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @param list<int> $ids */
    public function markNotifiedByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isNotified', ':yes')
            ->where('n.id IN (:ids)')
            ->setParameter('yes', true)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
