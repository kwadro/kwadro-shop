<?php

namespace App\Entity;

use App\Repository\HomepagePromoStripItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HomepagePromoStripItemRepository::class)]
#[ORM\Table(name: 'shop_homepage_promo_strip_item')]
class HomepagePromoStripItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomepageSetting::class, inversedBy: 'promoStripItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HomepageSetting $homepageSetting = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 999)]
    private int $position = 0;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $content = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHomepageSetting(): ?HomepageSetting
    {
        return $this->homepageSetting;
    }

    public function setHomepageSetting(?HomepageSetting $homepageSetting): static
    {
        $this->homepageSetting = $homepageSetting;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = max(0, min(999, $position ?? 0));

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = trim((string) $content);

        return $this;
    }

    public function __toString(): string
    {
        $plain = trim(strip_tags($this->content));
        if ($plain === '') {
            return sprintf('Promo item #%s', $this->id ?? '?');
        }

        return mb_strlen($plain) > 48 ? mb_substr($plain, 0, 45).'…' : $plain;
    }
}
