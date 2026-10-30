<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\ContentAgentAction;
use App\AiContentAgent\Entity\ContentTopic;
use App\AiContentAgent\Repository\ContentTopicRepository;
use App\Entity\BlogArticle;
use Doctrine\ORM\EntityManagerInterface;

final class TopicRegistry
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentTopicRepository $topics,
        private readonly ContentAgentLogger $logger,
    ) {
    }

    public function find(string $title): ?ContentTopic
    {
        return $this->topics->findOneByTopicKey(TopicKey::fromTitle($title));
    }

    public function isProcessed(string $title): bool
    {
        return $this->find($title)?->isProcessed() === true;
    }

    public function register(string $title, int $sourceCount): ContentTopic
    {
        $title = trim($title);
        $key = TopicKey::fromTitle($title);
        $existing = $this->topics->findOneByTopicKey($key);
        if ($existing !== null) {
            if ($sourceCount > $existing->getSourceCount()) {
                $existing->setSourceCount($sourceCount);
                $this->entityManager->flush();
            }

            return $existing;
        }

        $topic = (new ContentTopic())
            ->setTopicKey($key)
            ->setTitle(mb_substr($title, 0, 255))
            ->setStatus(ContentTopic::STATUS_OPEN)
            ->setSourceCount($sourceCount);

        $this->entityManager->persist($topic);
        $this->entityManager->flush();
        $this->logger->info(ContentAgentAction::TOPIC_REGISTERED, 'Topic registered.', [
            'topicId' => $topic->getId(),
            'title' => $topic->getTitle(),
        ]);

        return $topic;
    }

    public function markProcessed(ContentTopic $topic, BlogArticle $article): void
    {
        $topic
            ->setStatus(ContentTopic::STATUS_PROCESSED)
            ->setBlogArticle($article)
            ->setProcessedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->logger->info(ContentAgentAction::TOPIC_PROCESSED, 'Topic marked as processed.', [
            'topicId' => $topic->getId(),
            'articleId' => $article->getId(),
            'slug' => $article->getSlug(),
        ]);
    }
}
