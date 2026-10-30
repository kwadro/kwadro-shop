<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\ContentAgentAction;
use App\AiContentAgent\Dto\DiscoveredSource;
use App\AiContentAgent\Entity\ContentSource;
use App\AiContentAgent\Repository\ContentSourceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SourceHistoryStore
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentSourceRepository $sources,
        private readonly ContentAgentLogger $logger,
    ) {
    }

    /**
     * @return array{source: ContentSource, created: bool}
     */
    public function remember(DiscoveredSource $discovered, ?string $topicKey): array
    {
        $url = $this->safeUrl($discovered->url);
        if ($url === null) {
            $this->logger->warning(ContentAgentAction::SOURCE_REJECTED, 'Source URL was rejected.', [
                'url' => mb_substr(trim($discovered->url), 0, 300),
            ]);

            throw new \InvalidArgumentException('Source URL must be an http or https address.');
        }

        $hash = TopicKey::fromUrl($url);
        $existing = $this->sources->findOneByUrlHash($hash);
        $now = new \DateTimeImmutable();

        if ($existing !== null) {
            $existing->setLastSeenAt($now);
            if ($topicKey !== null && $existing->getTopicKey() === null) {
                $existing->setTopicKey($topicKey);
            }
            if (($existing->getExcerpt() === null || $existing->getExcerpt() === '') && trim($discovered->excerpt) !== '') {
                $existing->setExcerpt($this->clip($discovered->excerpt, 4000));
            }
            if (($existing->getTitle() === null || $existing->getTitle() === '') && trim($discovered->title) !== '') {
                $existing->setTitle($this->clip($discovered->title, 255));
            }
            $this->entityManager->flush();
            $this->logger->info(ContentAgentAction::SOURCE_DUPLICATE, 'Source already stored. History was kept.', [
                'sourceId' => $existing->getId(),
                'url' => $url,
            ]);

            return ['source' => $existing, 'created' => false];
        }

        $source = (new ContentSource())
            ->setUrlHash($hash)
            ->setUrl($url)
            ->setTitle($this->clip($discovered->title, 255))
            ->setExcerpt($this->clip($discovered->excerpt, 4000))
            ->setTopicKey($topicKey)
            ->setDiscoveredAt($now)
            ->setLastSeenAt($now);

        $this->entityManager->persist($source);
        $this->entityManager->flush();
        $this->logger->info(ContentAgentAction::SOURCE_RECORDED, 'Source stored in history.', [
            'sourceId' => $source->getId(),
            'url' => $url,
            'topicKey' => $topicKey,
        ]);

        return ['source' => $source, 'created' => true];
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    private function clip(string $value, int $max): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max);
        }

        return $value;
    }
}
