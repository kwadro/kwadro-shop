<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\CategoryRepository;
use App\Routing\ShopRoutes;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'shop_category')]
#[ORM\UniqueConstraint(name: 'uniq_shop_category_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Category
{
    use TimeStampAbleTrait;

    public const DEFAULT_NAME = 'Default';
    public const DEFAULT_SLUG = 'default';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\Regex(
        pattern: '#^(?:'.ShopRoutes::SLUG_REQUIREMENTS.')?$#',
        message: 'Slug may contain only letters, digits, hyphen and underscore.',
    )]
    private string $slug = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $meta_title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $meta_description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $level = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'categories')]
    private Collection $products;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug);

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

    public function getMetaTitle(): ?string
    {
        return $this->meta_title;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $metaTitle = $metaTitle !== null ? trim($metaTitle) : null;
        $this->meta_title = $metaTitle !== '' ? $metaTitle : null;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->meta_description;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $metaDescription = $metaDescription !== null ? trim($metaDescription) : null;
        $this->meta_description = $metaDescription !== '' ? $metaDescription : null;

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = max(0, $level);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = max(0, $position);

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        $this->recalculateLevel();

        return $this;
    }

    /** @return Collection<int, self> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function isDefault(): bool
    {
        return $this->slug === self::DEFAULT_SLUG || $this->name === self::DEFAULT_NAME;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function recalculateLevel(): void
    {
        $this->level = $this->parent instanceof self
            ? $this->parent->getLevel() + 1
            : 0;
    }

    public function __toString(): string
    {
        if ($this->name === '') {
            return 'Category';
        }

        return str_repeat('— ', $this->level) . $this->name;
    }
}
