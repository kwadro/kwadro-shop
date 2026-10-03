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

    /** Highest numeric IMAP UID already stored for this account. */
    public function findMaxRemoteUid(MailboxAccount $mailbox): ?int
    {
        // remote_uid is a string column: order by length then value for numeric max.
        $rows = $this->createQueryBuilder('m')
            ->select('m.remoteUid')
            ->andWhere('m.mailbox = :mailbox')
            ->andWhere('m.remoteUid != :empty')
            ->setParameter('mailbox', $mailbox)
            ->setParameter('empty', '')
            ->orderBy('LENGTH(m.remoteUid)', 'DESC')
            ->addOrderBy('m.remoteUid', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleColumnResult();

        $uid = isset($rows[0]) ? (string) $rows[0] : '';

        return $uid !== '' && ctype_digit($uid) ? (int) $uid : null;
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
