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

    /** Request path used for redirect lookup (pathInfo). */
    #[ORM\Column(length: 2048)]
    private string $path = '';

    /** Target URL when a redirect was applied for this request. */
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $pathAfterRedirect = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

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
        $this->path = $this->truncate($path, 2048);

        return $this;
    }

    public function getPathAfterRedirect(): ?string
    {
        return $this->pathAfterRedirect;
    }

    public function setPathAfterRedirect(?string $pathAfterRedirect): static
    {
        if ($pathAfterRedirect === null) {
            $this->pathAfterRedirect = null;

            return $this;
        }

        $normalized = $this->truncate($pathAfterRedirect, 2048);
        $this->pathAfterRedirect = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        if ($userAgent === null) {
            $this->userAgent = null;

            return $this;
        }

        $normalized = $this->truncate($userAgent, 512);
        $this->userAgent = $normalized !== '' ? $normalized : null;

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

    private function truncate(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max);
        }

        return $value;
    }
}
