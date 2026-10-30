<?php

namespace App\AiContentAgent\Entity;

use App\AiContentAgent\Repository\ContentSourceRepository;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentSourceRepository::class)]
#[ORM\Table(name: 'ai_content_source')]
#[ORM\UniqueConstraint(name: 'uniq_ai_content_source_url_hash', columns: ['url_hash'])]
#[ORM\Index(name: 'idx_ai_content_source_topic', columns: ['topic_key'])]
#[ORM\HasLifecycleCallbacks]
class ContentSource
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $urlHash = '';

    #[ORM\Column(length: 2048)]
    private string $url = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $topicKey = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $discoveredAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->discoveredAt = $now;
        $this->lastSeenAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrlHash(): string
    {
        return $this->urlHash;
    }

    public function setUrlHash(string $urlHash): static
    {
        $this->urlHash = $urlHash;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $title = $title !== null ? trim($title) : null;
        $this->title = $title !== '' ? $title : null;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $excerpt = $excerpt !== null ? trim($excerpt) : null;
        $this->excerpt = $excerpt !== '' ? $excerpt : null;

        return $this;
    }

    public function getTopicKey(): ?string
    {
        return $this->topicKey;
    }

    public function setTopicKey(?string $topicKey): static
    {
        $topicKey = $topicKey !== null ? trim($topicKey) : null;
        $this->topicKey = $topicKey !== '' ? $topicKey : null;

        return $this;
    }

    public function getDiscoveredAt(): \DateTimeImmutable
    {
        return $this->discoveredAt;
    }

    public function setDiscoveredAt(\DateTimeImmutable $discoveredAt): static
    {
        $this->discoveredAt = $discoveredAt;

        return $this;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title !== null && $this->title !== '' ? $this->title : $this->url;
    }
}
