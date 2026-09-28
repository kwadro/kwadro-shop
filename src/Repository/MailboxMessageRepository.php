<?php

namespace App\Repository;

use App\Entity\MailboxAccount;
use App\Entity\MailboxMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MailboxMessage> */
class MailboxMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailboxMessage::class);
    }

    public function findOneByMailboxAndUid(MailboxAccount $mailbox, string $remoteUid): ?MailboxMessage
    {
        /** @var MailboxMessage|null $message */
        $message = $this->findOneBy([
            'mailbox' => $mailbox,
            'remoteUid' => $remoteUid,
        ]);

        return $message;
    }

    /** @return list<MailboxMessage> */
    public function findUnnotified(int $limit = 20): array
    {
        /** @var list<MailboxMessage> $items */
        $items = $this->createQueryBuilder('m')
            ->innerJoin('m.mailbox', 'a')
            ->andWhere('a.isActive = true')
            ->andWhere('m.isNotified = false')
            ->orderBy('m.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function countUnread(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.mailbox', 'a')
            ->andWhere('a.isActive = true')
            ->andWhere('m.isSeen = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<int> $ids */
    public function markNotifiedByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.isNotified', ':yes')
            ->where('m.id IN (:ids)')
            ->setParameter('yes', true)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
