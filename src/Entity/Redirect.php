<?php

namespace App\Entity;

use App\Repository\RedirectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RedirectRepository::class)]
#[ORM\Table(name: 'shop_redirect')]
#[ORM\UniqueConstraint(name: 'uniq_shop_redirect_from_path', columns: ['from_path'])]
#[ORM\Index(name: 'idx_shop_redirect_type_enabled', columns: ['type', 'enabled'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['fromPath'], message: 'admin.redirect.from_path_unique')]
class Redirect
{
    use Traits\TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16, enumType: RedirectType::class, options: ['default' => 'base'])]
    private RedirectType $type = RedirectType::Base;

    /** Source path, e.g. /efirne-tb.html */
    #[ORM\Column(length: 512)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 512)]
    private string $fromPath = '';

    /** Destination path or absolute URL. Empty = not active yet. */
    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    private ?string $toPath = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 301])]
    #[Assert\Choice(choices: [301, 302, 307, 308])]
    private int $statusCode = 301;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): RedirectType
    {
        return $this->type;
    }

    public function setType(RedirectType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFromPath(): string
    {
        return $this->fromPath;
    }

    public function setFromPath(string $fromPath): static
    {
        $this->fromPath = self::normalizePath($fromPath);

        return $this;
    }

    public function getToPath(): ?string
    {
        return $this->toPath;
    }

    public function setToPath(?string $toPath): static
    {
        $toPath = $toPath !== null ? trim($toPath) : null;
        if ($toPath === null || $toPath === '') {
            $this->toPath = null;

            return $this;
        }

        if (preg_match('#^https?://#i', $toPath) === 1) {
            $this->toPath = $toPath;

            return $this;
        }

        $this->toPath = self::normalizePath($toPath);

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function isReady(): bool
    {
        return $this->enabled && $this->toPath !== null && $this->toPath !== '';
    }

    public static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = \is_string($parsed) && $parsed !== '' ? $parsed : '/';
        }

        $path = '/'.ltrim($path, '/');
        if (mb_strlen($path) > 512) {
            $path = mb_substr($path, 0, 512);
        }

        return $path;
    }

    public function __toString(): string
    {
        return $this->fromPath !== '' ? $this->fromPath : 'Redirect';
    }
}
