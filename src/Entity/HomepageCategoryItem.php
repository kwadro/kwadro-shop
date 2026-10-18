<?php

namespace App\Entity;

use App\Repository\HomepageCategoryItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HomepageCategoryItemRepository::class)]
#[ORM\Table(name: 'shop_homepage_category_item')]
class HomepageCategoryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomepageSetting::class, inversedBy: 'categoryItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HomepageSetting $homepageSetting = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Category $category = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 999)]
    private int $position = 0;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

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

    public function __toString(): string
    {
        $name = $this->category?->getName() ?? 'item';

        return sprintf('%s (#%d)', $name, $this->position);
    }
}
