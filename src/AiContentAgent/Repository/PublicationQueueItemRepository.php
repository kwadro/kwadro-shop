<?php

namespace App\AiContentAgent\Repository;

use App\AiContentAgent\Entity\ContentTopic;
use App\AiContentAgent\Entity\PublicationQueueItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PublicationQueueItem> */
class PublicationQueueItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationQueueItem::class);
    }

    public function findOpenForTopic(ContentTopic $topic): ?PublicationQueueItem
    {
        /** @var PublicationQueueItem|null $item */
        $item = $this->createQueryBuilder('q')
            ->andWhere('q.topic = :topic')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('topic', $topic)
            ->setParameter('statuses', [
                PublicationQueueItem::STATUS_QUEUED,
                PublicationQueueItem::STATUS_GENERATING,
            ])
            ->orderBy('q.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $item;
    }

    /**
     * @param list<string> $statuses
     *
     * @return list<PublicationQueueItem>
     */
    public function findDue(\DateTimeImmutable $now, array $statuses, int $limit): array
    {
        if ($statuses === [] || $limit < 1) {
            return [];
        }

        /** @var list<PublicationQueueItem> $items */
        $items = $this->createQueryBuilder('q')
            ->andWhere('q.status IN (:statuses)')
            ->andWhere('q.scheduledAt <= :now')
            ->setParameter('statuses', $statuses)
            ->setParameter('now', $now)
            ->orderBy('q.scheduledAt', 'ASC')
            ->addOrderBy('q.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $items;
    }

    /** @return list<PublicationQueueItem> */
    public function findPublishedUnnotified(int $limit = 20): array
    {
        /** @var list<PublicationQueueItem> $items */
        $items = $this->createQueryBuilder('q')
            ->andWhere('q.status = :status')
            ->andWhere('q.notifiedAt IS NULL')
            ->setParameter('status', PublicationQueueItem::STATUS_PUBLISHED)
            ->orderBy('q.publishedAt', 'ASC')
            ->addOrderBy('q.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $items;
    }
}
