<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\AdminShopNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminShopNotificationRepository::class)]
#[ORM\Table(name: 'shop_admin_shop_notification')]
#[ORM\Index(name: 'idx_admin_shop_notify_pending', columns: ['is_notified', 'created_at'])]
#[ORM\Index(name: 'idx_admin_shop_notify_entity', columns: ['channel', 'entity_id', 'is_notified'])]
#[ORM\HasLifecycleCallbacks]
class AdminShopNotification
{
    use TimeStampAbleTrait;

    public const CHANNEL_CART = 'cart';
    public const CHANNEL_ORDER = 'order';
    public const CHANNEL_PAYMENT = 'payment';
    public const CHANNEL_REQUESTS = 'requests';

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_MILESTONE = 'milestone';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $channel = '';

    #[ORM\Column(length: 32)]
    private string $action = '';

    #[ORM\Column]
    private int $entityId = 0;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $targetPath = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isNotified = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = trim($channel);

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = trim($action);

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = mb_substr(trim($title), 0, 255);

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = trim($body);

        return $this;
    }

    public function getTargetPath(): ?string
    {
        return $this->targetPath;
    }

    public function setTargetPath(?string $targetPath): static
    {
        $normalized = $targetPath !== null ? trim($targetPath) : null;
        if ($normalized !== null && mb_strlen($normalized) > 512) {
            $normalized = mb_substr($normalized, 0, 509).'...';
        }
        $this->targetPath = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function isNotified(): bool
    {
        return $this->isNotified;
    }

    public function setIsNotified(bool $isNotified): static
    {
        $this->isNotified = $isNotified;

        return $this;
    }
}
