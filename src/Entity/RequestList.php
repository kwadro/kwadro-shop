<?php

namespace App\Entity;

use App\Repository\RequestListRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestListRepository::class)]
#[ORM\Table(name: 'shop_request_list')]
#[ORM\Index(name: 'idx_request_list_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_request_list_ip_created', columns: ['ip', 'created_at'])]
class RequestList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 2048)]
    private string $path = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $ip = $ip !== null ? trim($ip) : null;
        $this->ip = $ip !== '' ? $ip : null;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $path = trim($path);
        if (mb_strlen($path) > 2048) {
            $path = mb_substr($path, 0, 2048);
        }
        $this->path = $path;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->ip ?? '—', $this->path);
    }
}
