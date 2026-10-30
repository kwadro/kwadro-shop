<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\ContentAgentAction;
use App\AiContentAgent\Dto\ContentAgentReport;
use App\AiContentAgent\Dto\ContentAgentRequest;
use App\AiContentAgent\Dto\DiscoveredSource;
use App\AiContentAgent\Entity\ContentTopic;
use App\AiContentAgent\Entity\PublicationQueueItem;
use App\AiContentAgent\Repository\ContentSourceRepository;

/**
 * AI Content Agent.
 *
 * Stores found sources, skips topics that were already processed, queues future
 * publications, generates an SEO article, publishes it, runs the Deployer task,
 * logs every step, and announces a successful publication.
 */
final class ContentAgent
{
    public function __construct(
        private readonly ContentAgentLogger $logger,
        private readonly SourceHistoryStore $sourceHistory,
        private readonly TopicRegistry $topics,
        private readonly PublicationQueue $queue,
        private readonly ContentSourceRepository $sources,
        private readonly SeoArticleGenerator $generator,
        private readonly ArticlePublisher $publisher,
        private readonly DeployerTaskRunner $deployer,
        private readonly PublicationNotifier $notifier,
        private readonly string $localeCode,
    ) {
    }

    public function run(ContentAgentRequest $request): ContentAgentReport
    {
        $intakes = trim($request->topicTitle) === '' ? [] : [$request];

        return $this->executeBatch(
            $intakes,
            $request->scheduleOnly,
            $request->limit,
            $request->retryFailed,
            $request->deploy,
        );
    }

    /**
     * @param list<ContentAgentRequest> $intakes
     */
    public function executeBatch(
        array $intakes,
        bool $scheduleOnly,
        int $limit,
        bool $retryFailed,
        ?bool $deploy,
    ): ContentAgentReport {
        $report = new ContentAgentReport();
        $this->logger->info(ContentAgentAction::RUN_STARTED, 'Content agent run started.', [
            'topics' => count($intakes),
            'scheduleOnly' => $scheduleOnly,
            'limit' => $limit,
        ]);

        foreach ($intakes as $request) {
            try {
                $report->absorb($this->intake($request));
            } catch (\Throwable $exception) {
                $this->logger->error(ContentAgentAction::RUN_FAILED, $exception->getMessage(), [
                    'exception' => $exception::class,
                    'topic' => $request->topicTitle,
                ]);
                $report->failures[] = $exception->getMessage();
            }
        }

        if (!$scheduleOnly) {
            try {
                $report->absorb($this->publishDue($limit, $retryFailed, $deploy));
            } catch (\Throwable $exception) {
                $this->logger->error(ContentAgentAction::RUN_FAILED, $exception->getMessage(), [
                    'exception' => $exception::class,
                ]);
                $report->failures[] = $exception->getMessage();
            }
        }

        $this->logger->info(ContentAgentAction::RUN_FINISHED, 'Content agent run finished.', [
            'queued' => $report->topicsQueued,
            'skipped' => $report->topicsSkipped,
            'published' => count($report->published),
            'failed' => count($report->failures),
            'deploy' => $report->deployStatus,
            'notified' => $report->notified,
        ]);

        return $report;
    }

    public function intake(ContentAgentRequest $request): ContentAgentReport
    {
        $report = new ContentAgentReport();
        $title = trim($request->topicTitle);
        $this->assertTopic($title);

        if ($this->topics->isProcessed($title)) {
            $this->rememberSources($request->sources, TopicKey::fromTitle($title), $report);
            $this->logger->info(ContentAgentAction::TOPIC_SKIPPED, 'Topic was already processed. Generation was skipped.', [
                'title' => $title,
            ]);
            ++$report->topicsSkipped;
            $report->notes[] = 'Skipped already processed topic: '.$title;

            return $report;
        }

        $topicKey = TopicKey::fromTitle($title);
        $recorded = $this->rememberSources($request->sources, $topicKey, $report);
        $topic = $this->topics->register($title, $recorded);
        if ($topic->isProcessed()) {
            $this->logger->info(ContentAgentAction::TOPIC_SKIPPED, 'Topic was already processed. Generation was skipped.', [
                'topicId' => $topic->getId(),
                'title' => $title,
            ]);
            ++$report->topicsSkipped;
            $report->notes[] = 'Skipped already processed topic: '.$title;

            return $report;
        }

        $queued = $this->queue->enqueue($topic, $request->scheduledAt);
        if ($queued['created']) {
            ++$report->topicsQueued;
            $report->notes[] = sprintf(
                'Queued "%s" for %s.',
                $topic->getTitle(),
                $request->scheduledAt->format('Y-m-d H:i'),
            );
        } else {
            $report->notes[] = 'Topic already waiting in the publication queue: '.$topic->getTitle();
        }

        return $report;
    }

    public function publishDue(int $limit, bool $retryFailed, ?bool $deploy): ContentAgentReport
    {
        $report = new ContentAgentReport();
        $limit = max(1, min(10, $limit));
        $due = $this->queue->findDue(new \DateTimeImmutable(), $limit, $retryFailed);

        if ($due === []) {
            $this->logger->info(ContentAgentAction::QUEUE_EMPTY, 'No publications are due.');
            $report->notes[] = 'No publications are due.';
        }

        foreach ($due as $item) {
            $this->publishItem($item, $report);
        }

        $this->finalizeDeployAndNotify($deploy, $report);

        return $report;
    }

    private function publishItem(PublicationQueueItem $item, ContentAgentReport $report): void
    {
        $topic = $item->getTopic();
        if (!$topic instanceof ContentTopic) {
            $this->queue->markFailed($item, 'Queue item has no topic.');
            $report->failures[] = 'Queue item #'.$item->getId().' has no topic.';

            return;
        }

        if ($topic->isProcessed()) {
            $article = $topic->getBlogArticle() ?? $item->getBlogArticle();
            $this->logger->info(ContentAgentAction::TOPIC_SKIPPED, 'Topic was already processed. Generation was skipped.', [
                'topicId' => $topic->getId(),
                'queueItemId' => $item->getId(),
            ], $item->getId());
            if ($article !== null) {
                $item->setBlogArticle($article);
                $this->queue->markPublished($item, $article->getSlug());
                $report->notes[] = 'Kept the existing article for a processed topic: '.$article->getSlug();
            } else {
                $this->queue->markFailed($item, 'Topic was already processed.');
            }
            ++$report->topicsSkipped;

            return;
        }

        try {
            $this->queue->markGenerating($item);
            $sources = $this->sourcesForTopic($topic);
            $draft = $this->generator->generate($topic->getTitle(), $sources, $this->localeCode);
            foreach ($draft->warnings as $warning) {
                $this->logger->warning(ContentAgentAction::ARTICLE_GENERATED, $warning, [
                    'queueItemId' => $item->getId(),
                ], $item->getId());
                $report->notes[] = $warning;
            }
            $this->logger->info(ContentAgentAction::ARTICLE_GENERATED, 'SEO article generated.', [
                'queueItemId' => $item->getId(),
                'slug' => $draft->slug,
                'metaTitle' => $draft->metaTitle,
            ], $item->getId());

            $article = $this->publisher->publish($item, $draft);
            $this->topics->markProcessed($topic, $article);
            $this->queue->markPublished($item, $article->getSlug());
            $report->published[] = $article->getSlug();
        } catch (\Throwable $exception) {
            $this->queue->markFailed($item, $exception->getMessage());
            $report->failures[] = ($topic->getTitle() !== '' ? $topic->getTitle().': ' : '').$exception->getMessage();
        }
    }

    private function finalizeDeployAndNotify(?bool $deploy, ContentAgentReport $report): void
    {
        $pending = $this->queue->findPublishedUnnotified();
        if ($pending === []) {
            return;
        }

        $result = $this->deployer->run($deploy);
        $report->deployStatus = $result->status;

        if (!$result->allowsNotification()) {
            $error = $result->error !== '' ? $result->error : 'Deployer task failed.';
            foreach ($pending as $item) {
                $this->queue->rememberDeployError($item, $error);
            }
            $report->failures[] = $error;

            return;
        }

        foreach ($pending as $item) {
            try {
                $this->notifier->notify($item);
                $this->queue->markNotified($item);
                ++$report->notified;
            } catch (\Throwable $exception) {
                $this->logger->error(ContentAgentAction::NOTIFY_FAILED, $exception->getMessage(), [
                    'queueItemId' => $item->getId(),
                ], $item->getId());
                $report->failures[] = 'Notification failed: '.$exception->getMessage();
            }
        }
    }

    /**
     * @param list<DiscoveredSource> $sources
     */
    private function rememberSources(array $sources, string $topicKey, ContentAgentReport $report): int
    {
        $stored = 0;
        foreach ($sources as $source) {
            try {
                $result = $this->sourceHistory->remember($source, $topicKey);
            } catch (\InvalidArgumentException $exception) {
                $report->notes[] = $exception->getMessage();
                continue;
            }

            if ($result['created']) {
                ++$report->sourcesRecorded;
            } else {
                ++$report->sourcesDuplicate;
            }
            ++$stored;
        }

        return $stored;
    }

    /** @return list<DiscoveredSource> */
    private function sourcesForTopic(ContentTopic $topic): array
    {
        $sources = [];
        foreach ($this->sources->findByTopicKey($topic->getTopicKey()) as $source) {
            $sources[] = new DiscoveredSource(
                $source->getUrl(),
                (string) $source->getTitle(),
                (string) $source->getExcerpt(),
            );
        }

        return $sources;
    }

    private function assertTopic(string $title): void
    {
        $length = mb_strlen($title);
        if ($length < 3 || $length > 255) {
            throw new \InvalidArgumentException('Topic must be between 3 and 255 characters.');
        }
    }
}
