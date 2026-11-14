<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\BotUserAgentMarkerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BotUserAgentMarkerRepository::class)]
#[ORM\Table(name: 'shop_bot_user_agent_marker')]
#[ORM\UniqueConstraint(name: 'uniq_shop_bot_user_agent_marker', columns: ['marker'])]
#[ORM\HasLifecycleCallbacks]
class BotUserAgentMarker
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Substring matched case-insensitively against RequestList User-Agent. */
    #[ORM\Column(length: 128)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 128)]
    private string $marker = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMarker(): string
    {
        return $this->marker;
    }

    public function setMarker(string $marker): static
    {
        $this->marker = mb_strtolower(trim($marker));

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
        return $this->marker !== '' ? $this->marker : 'Bot UA marker';
    }
}
