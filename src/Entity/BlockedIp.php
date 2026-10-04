<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\BlockedIpRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlockedIpRepository::class)]
#[ORM\Table(name: 'shop_blocked_ip')]
#[ORM\UniqueConstraint(name: 'uniq_shop_blocked_ip', columns: ['ip'])]
#[ORM\HasLifecycleCallbacks]
class BlockedIp
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    #[Assert\NotBlank]
    #[Assert\Ip(version: Assert\Ip::ALL)]
    #[Assert\Length(max: 45)]
    private string $ip = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = trim($ip);

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $normalized = $note !== null ? trim($note) : null;
        $this->note = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function __toString(): string
    {
        return $this->ip !== '' ? $this->ip : 'Blocked IP';
    }
}
