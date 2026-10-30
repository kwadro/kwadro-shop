<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\ContentAgentAction;
use App\AiContentAgent\Entity\ContentTopic;
use App\AiContentAgent\Entity\PublicationQueueItem;
use App\AiContentAgent\Repository\PublicationQueueItemRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PublicationQueue
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationQueueItemRepository $items,
        private readonly ContentAgentLogger $logger,
    ) {
    }

    /**
     * @return array{item: PublicationQueueItem, created: bool}
     */
    public function enqueue(ContentTopic $topic, \DateTimeImmutable $scheduledAt): array
    {
        $open = $this->items->findOpenForTopic($topic);
        if ($open !== null) {
            $this->logger->info(ContentAgentAction::QUEUE_DUPLICATE, 'Topic already has an open publication.', [
                'topicId' => $topic->getId(),
                'queueItemId' => $open->getId(),
                'status' => $open->getStatus(),
            ], $open->getId());

            return ['item' => $open, 'created' => false];
        }

        $item = (new PublicationQueueItem())
            ->setTopic($topic)
            ->setScheduledAt($scheduledAt)
            ->setStatus(PublicationQueueItem::STATUS_QUEUED);

        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $this->logger->info(ContentAgentAction::QUEUE_ENQUEUED, 'Publication queued.', [
            'topicId' => $topic->getId(),
            'queueItemId' => $item->getId(),
            'scheduledAt' => $scheduledAt->format('c'),
        ], $item->getId());

        return ['item' => $item, 'created' => true];
    }

    /** @return list<PublicationQueueItem> */
    public function findDue(\DateTimeImmutable $now, int $limit, bool $retryFailed): array
    {
        $statuses = [
            PublicationQueueItem::STATUS_QUEUED,
            PublicationQueueItem::STATUS_GENERATING,
        ];
        if ($retryFailed) {
            $statuses[] = PublicationQueueItem::STATUS_FAILED;
        }

        return $this->items->findDue($now, $statuses, $limit);
    }

    /** @return list<PublicationQueueItem> */
    public function findPublishedUnnotified(): array
    {
        return $this->items->findPublishedUnnotified();
    }

    public function markGenerating(PublicationQueueItem $item): void
    {
        $item
            ->setStatus(PublicationQueueItem::STATUS_GENERATING)
            ->setAttemptCount($item->getAttemptCount() + 1)
            ->setLastError(null);
        $this->entityManager->flush();
        $this->logger->info(ContentAgentAction::ARTICLE_GENERATING, 'Publication moved to generating.', [
            'queueItemId' => $item->getId(),
            'attempt' => $item->getAttemptCount(),
        ], $item->getId());
    }

    public function markPublished(PublicationQueueItem $item, string $slug): void
    {
        $item
            ->setStatus(PublicationQueueItem::STATUS_PUBLISHED)
            ->setSlug($slug)
            ->setPublishedAt(new \DateTimeImmutable())
            ->setLastError(null);
        $this->entityManager->flush();
    }

    public function markFailed(PublicationQueueItem $item, string $error): void
    {
        $item
            ->setStatus(PublicationQueueItem::STATUS_FAILED)
            ->setLastError(mb_substr($error, 0, 4000));
        $this->entityManager->flush();
        $this->logger->error(ContentAgentAction::ITEM_FAILED, $error, [
            'queueItemId' => $item->getId(),
            'attempt' => $item->getAttemptCount(),
        ], $item->getId());
    }

    public function markNotified(PublicationQueueItem $item): void
    {
        $item->setNotifiedAt(new \DateTimeImmutable());
        $item->setLastError(null);
        $this->entityManager->flush();
    }

    public function rememberDeployError(PublicationQueueItem $item, string $error): void
    {
        $item->setLastError(mb_substr($error, 0, 4000));
        $this->entityManager->flush();
    }
}
