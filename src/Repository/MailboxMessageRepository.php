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
            ->andWhere('m.folder = :folder')
            ->andWhere('m.remoteUid != :empty')
            ->setParameter('mailbox', $mailbox)
            ->setParameter('folder', MailboxMessage::FOLDER_INBOX)
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
            ->andWhere('m.folder = :folder')
            ->andWhere('m.isNotified = false')
            ->setParameter('folder', MailboxMessage::FOLDER_INBOX)
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
            ->andWhere('m.folder = :folder')
            ->andWhere('m.isSeen = false')
            ->setParameter('folder', MailboxMessage::FOLDER_INBOX)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Unread messages received after a given moment (previous admin login).
     * When $since is null, counts all unread (first admin login ever).
     */
    public function countUnreadReceivedAfter(?\DateTimeInterface $since): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.mailbox', 'a')
            ->andWhere('a.isActive = true')
            ->andWhere('m.folder = :folder')
            ->andWhere('m.isSeen = false')
            ->setParameter('folder', MailboxMessage::FOLDER_INBOX);

        if ($since !== null) {
            $qb->andWhere('m.receivedAt > :since')
                ->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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

    public function findOneByMessageId(string $messageId): ?MailboxMessage
    {
        $normalized = strtolower(trim($messageId, " \t\r\n<>"));
        if ($normalized === '') {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            strtolower(trim($messageId)),
            $normalized,
            '<'.$normalized.'>',
        ])));

        /** @var MailboxMessage|null $message */
        $message = $this->createQueryBuilder('m')
            ->andWhere('LOWER(m.messageId) IN (:ids)')
            ->setParameter('ids', $candidates)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $message;
    }

    /**
     * Give a reply and the message it answers the same conversation id.
     * Reuses an id already present on the parent thread when one exists.
     */
    public function shareConversation(MailboxMessage $reply, MailboxMessage $parent): void
    {
        $conversationId = $this->findThreadConversationId($parent)
            ?? $this->findThreadConversationId($reply)
            ?? $this->newConversationId();

        $reply->setConversationId($conversationId);
        $this->stampConversation($parent, $conversationId);
    }

    /** Assign a fresh conversation id when starting a new thread (no parent reply). */
    public function startConversation(MailboxMessage $message): void
    {
        if ($message->getConversationId() !== null && $message->getConversationId() !== '') {
            return;
        }

        $message->setConversationId($this->newConversationId());
    }

    public function inheritLinkIfEmpty(MailboxMessage $target, MailboxMessage $source): void
    {
        $this->copyLinkIfEmpty($target, $source);
    }

    /**
     * @return list<array{
     *     conversationId: string,
     *     messageCount: int,
     *     lastAt: \DateTimeInterface,
     *     subject: string,
     *     linkType: ?string,
     *     linkRef: ?string,
     *     correspondent: string
     * }>
     */
    public function findConversationSummaries(): array
    {
        /** @var list<MailboxMessage> $messages */
        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.conversationId IS NOT NULL')
            ->orderBy('m.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $groups = [];
        foreach ($messages as $message) {
            $conversationId = (string) $message->getConversationId();
            if (!isset($groups[$conversationId])) {
                $groups[$conversationId] = [
                    'conversationId' => $conversationId,
                    'messageCount' => 0,
                    'lastAt' => $message->getReceivedAt(),
                    'subject' => $message->getSubject(),
                    'linkType' => null,
                    'linkRef' => null,
                    'correspondent' => $message->getFolder() === MailboxMessage::FOLDER_SENT
                        ? trim((string) $message->getToAddresses())
                        : $message->getFromDisplay(),
                ];
            }
            ++$groups[$conversationId]['messageCount'];
            if (
                $groups[$conversationId]['linkType'] === null
                && $message->getLinkType() !== null
                && $message->getLinkRef() !== null
            ) {
                $groups[$conversationId]['linkType'] = $message->getLinkType();
                $groups[$conversationId]['linkRef'] = $message->getLinkRef();
            }
        }

        return array_values($groups);
    }

    /**
     * Threads linked to an order, newest conversation first.
     * A thread is included when any message in it is linked to this order number.
     * A linked message without a conversation id is its own one-message thread.
     *
     * @return list<array{
     *     conversationId: ?string,
     *     subject: string,
     *     correspondent: string,
     *     messageCount: int,
     *     lastAt: \DateTimeImmutable,
     *     singleMessageId: ?int,
     *     singleMessageFolder: ?string
     * }>
     */
    public function findOrderConversationCards(string $orderNumber): array
    {
        $orderNumber = trim($orderNumber);
        if ($orderNumber === '') {
            return [];
        }

        /** @var list<MailboxMessage> $linked */
        $linked = $this->createQueryBuilder('m')
            ->andWhere('m.linkType = :linkType')
            ->andWhere('m.linkRef = :linkRef')
            ->setParameter('linkType', MailboxMessage::LINK_ORDER)
            ->setParameter('linkRef', $orderNumber)
            ->orderBy('m.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $conversationIds = [];
        $cards = [];
        foreach ($linked as $message) {
            $conversationId = $message->getConversationId();
            if ($conversationId !== null && $conversationId !== '') {
                $conversationIds[$conversationId] = true;
                continue;
            }

            $cards[] = [
                'conversationId' => null,
                'subject' => $message->getSubject(),
                'correspondent' => $this->correspondent($message),
                'messageCount' => 1,
                'lastAt' => $message->getReceivedAt(),
                'singleMessageId' => $message->getId(),
                'singleMessageFolder' => $message->getFolder(),
            ];
        }

        if ($conversationIds !== []) {
            /** @var list<MailboxMessage> $threadMessages */
            $threadMessages = $this->createQueryBuilder('m')
                ->andWhere('m.conversationId IN (:ids)')
                ->setParameter('ids', array_keys($conversationIds))
                ->orderBy('m.receivedAt', 'ASC')
                ->getQuery()
                ->getResult();

            $groups = [];
            foreach ($threadMessages as $message) {
                $conversationId = (string) $message->getConversationId();
                if (!isset($groups[$conversationId])) {
                    $groups[$conversationId] = [
                        'conversationId' => $conversationId,
                        'subject' => $message->getSubject(),
                        'correspondent' => $this->correspondent($message),
                        'messageCount' => 0,
                        'lastAt' => $message->getReceivedAt(),
                        'singleMessageId' => null,
                        'singleMessageFolder' => null,
                    ];
                }
                ++$groups[$conversationId]['messageCount'];
                $groups[$conversationId]['lastAt'] = $message->getReceivedAt();
                $groups[$conversationId]['correspondent'] = $this->correspondent($message);
            }

            $cards = array_merge($cards, array_values($groups));
        }

        usort(
            $cards,
            static fn (array $left, array $right): int => $right['lastAt'] <=> $left['lastAt'],
        );

        return $cards;
    }

    /** @return list<MailboxMessage> */
    public function findByConversationId(string $conversationId): array
    {
        /** @var list<MailboxMessage> $messages */
        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.conversationId = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $messages;
    }

    private function correspondent(MailboxMessage $message): string
    {
        if ($message->getFolder() === MailboxMessage::FOLDER_SENT) {
            return trim((string) $message->getToAddresses());
        }

        return $message->getFromDisplay();
    }

    private function findThreadConversationId(MailboxMessage $message): ?string
    {
        $current = $message;
        for ($guard = 0; $current !== null && $guard < 50; ++$guard) {
            $conversationId = $current->getConversationId();
            if ($conversationId !== null && $conversationId !== '') {
                return $conversationId;
            }
            $current = $current->getParent();
        }

        return null;
    }

    private function stampConversation(MailboxMessage $message, string $conversationId): void
    {
        $current = $message;
        for ($guard = 0; $current !== null && $guard < 50; ++$guard) {
            $current->setConversationId($conversationId);
            $current = $current->getParent();
        }
    }

    private function copyLinkIfEmpty(MailboxMessage $target, MailboxMessage $source): void
    {
        if ($target->getLinkType() !== null || $source->getLinkType() === null || $source->getLinkRef() === null) {
            return;
        }

        $target
            ->setLinkType($source->getLinkType())
            ->setLinkRef($source->getLinkRef());
    }

    private function newConversationId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Distinct message groups for admin filters (label => value).
     *
     * @return array<string, string>
     */
    public function findDistinctMessageGroupChoices(?string $folder = null): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->select('DISTINCT m.messageGroup')
            ->orderBy('m.messageGroup', 'ASC');
        if ($folder !== null && $folder !== '') {
            $queryBuilder
                ->andWhere('m.folder = :folder')
                ->setParameter('folder', $folder);
        }

        /** @var list<string> $groups */
        $groups = $queryBuilder
            ->getQuery()
            ->getSingleColumnResult();

        $choices = [];
        foreach ($groups as $group) {
            $group = trim((string) $group);
            if ($group === '') {
                continue;
            }
            $choices[$group] = $group;
        }

        return $choices;
    }
}
