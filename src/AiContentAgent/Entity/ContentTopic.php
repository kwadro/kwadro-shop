<?php

namespace App\AiContentAgent\Entity;

use App\AiContentAgent\Repository\ContentTopicRepository;
use App\Entity\BlogArticle;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentTopicRepository::class)]
#[ORM\Table(name: 'ai_content_topic')]
#[ORM\UniqueConstraint(name: 'uniq_ai_content_topic_key', columns: ['topic_key'])]
#[ORM\Index(name: 'idx_ai_content_topic_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class ContentTopic
{
    use TimeStampAbleTrait;

    public const STATUS_OPEN = 'open';
    public const STATUS_PROCESSED = 'processed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $topicKey = '';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(options: ['default' => 0])]
    private int $sourceCount = 0;

    #[ORM\ManyToOne(targetEntity: BlogArticle::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BlogArticle $blogArticle = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTopicKey(): string
    {
        return $this->topicKey;
    }

    public function setTopicKey(string $topicKey): static
    {
        $this->topicKey = $topicKey;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

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

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function getSourceCount(): int
    {
        return $this->sourceCount;
    }

    public function setSourceCount(int $sourceCount): static
    {
        $this->sourceCount = max(0, $sourceCount);

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

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title !== '' ? $this->title : 'Topic';
    }
}
