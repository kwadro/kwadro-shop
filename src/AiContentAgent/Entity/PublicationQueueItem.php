<?php

namespace App\AiContentAgent\Entity;

use App\AiContentAgent\Repository\PublicationQueueItemRepository;
use App\Entity\BlogArticle;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicationQueueItemRepository::class)]
#[ORM\Table(name: 'ai_content_publication')]
#[ORM\Index(name: 'idx_ai_content_publication_due', columns: ['status', 'scheduled_at'])]
#[ORM\HasLifecycleCallbacks]
class PublicationQueueItem
{
    use TimeStampAbleTrait;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ContentTopic::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ContentTopic $topic = null;

    #[ORM\ManyToOne(targetEntity: BlogArticle::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BlogArticle $blogArticle = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $attemptCount = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct()
    {
        $this->scheduledAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTopic(): ?ContentTopic
    {
        return $this->topic;
    }

    public function setTopic(ContentTopic $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    public function getBlogArticle(): ?BlogArticle
    {
        return $this->blogArticle;
    }

    public function setBlogArticle(?BlogArticle $blogArticle): static
    {
        $this->blogArticle = $blogArticle;

        return $this;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $slug = $slug !== null ? trim($slug) : null;
        $this->slug = $slug !== '' ? $slug : null;

        return $this;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function setAttemptCount(int $attemptCount): static
    {
        $this->attemptCount = max(0, $attemptCount);

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $lastError = $lastError !== null ? trim($lastError) : null;
        $this->lastError = $lastError !== '' ? $lastError : null;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): static
    {
        $this->notifiedAt = $notifiedAt;

        return $this;
    }

    public function __toString(): string
    {
        $title = $this->topic?->getTitle() ?? 'Publication';

        return $title.' @ '.$this->scheduledAt->format('Y-m-d H:i');
    }
}
