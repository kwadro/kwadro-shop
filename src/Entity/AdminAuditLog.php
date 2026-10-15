<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\AdminAuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminAuditLogRepository::class)]
#[ORM\Table(name: 'shop_admin_audit_log')]
#[ORM\Index(name: 'idx_admin_audit_account', columns: ['account_name'])]
#[ORM\Index(name: 'idx_admin_audit_action', columns: ['action'])]
#[ORM\Index(name: 'idx_admin_audit_created', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class AdminAuditLog
{
    use TimeStampAbleTrait;

    public const ACTION_LOGIN = 'login';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $accountName = '';

    #[ORM\Column(length: 32)]
    private string $action = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityClass = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $entityLabel = null;

    /** @var list<array{field: string, old: mixed, new: mixed}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $changes = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $requestPath = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public function setAccountName(string $accountName): static
    {
        $this->accountName = trim($accountName);

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

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function setEntityClass(?string $entityClass): static
    {
        $normalized = $entityClass !== null ? trim($entityClass) : null;
        $this->entityClass = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $normalized = $entityId !== null ? trim($entityId) : null;
        $this->entityId = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function setEntityLabel(?string $entityLabel): static
    {
        $normalized = $entityLabel !== null ? trim($entityLabel) : null;
        if ($normalized !== null && mb_strlen($normalized) > 512) {
            $normalized = mb_substr($normalized, 0, 509).'...';
        }
        $this->entityLabel = $normalized !== '' ? $normalized : null;

        return $this;
    }

    /**
     * @return list<array{field: string, old: mixed, new: mixed}>|null
     */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    /**
     * @param list<array{field: string, old: mixed, new: mixed}>|null $changes
     */
    public function setChanges(?array $changes): static
    {
        $this->changes = $changes === [] ? null : $changes;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $normalized = $ip !== null ? trim($ip) : null;
        $this->ip = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getRequestPath(): ?string
    {
        return $this->requestPath;
    }

    public function setRequestPath(?string $requestPath): static
    {
        $normalized = $requestPath !== null ? trim($requestPath) : null;
        if ($normalized !== null && mb_strlen($normalized) > 1024) {
            $normalized = mb_substr($normalized, 0, 1021).'...';
        }
        $this->requestPath = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('#%s %s %s', $this->id ?? '?', $this->action, $this->accountName);
    }
}
