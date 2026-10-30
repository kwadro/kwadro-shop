<?php

namespace App\AiContentAgent\Entity;

use App\AiContentAgent\Repository\ContentAgentLogRepository;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentAgentLogRepository::class)]
#[ORM\Table(name: 'ai_content_log')]
#[ORM\Index(name: 'idx_ai_content_log_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_ai_content_log_level', columns: ['level'])]
#[ORM\Index(name: 'idx_ai_content_log_action', columns: ['action'])]
#[ORM\HasLifecycleCallbacks]
class ContentAgentLog
{
    use TimeStampAbleTrait;

    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private string $level = self::LEVEL_INFO;

    #[ORM\Column(length: 64)]
    private string $action = '';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    #[ORM\Column(nullable: true)]
    private ?int $queueItemId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /** @param array<string, mixed>|null $context */
    public function setContext(?array $context): static
    {
        $this->context = $context === [] ? null : $context;

        return $this;
    }

    public function getQueueItemId(): ?int
    {
        return $this->queueItemId;
    }

    public function setQueueItemId(?int $queueItemId): static
    {
        $this->queueItemId = $queueItemId;

        return $this;
    }
}
