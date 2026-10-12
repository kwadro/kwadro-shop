<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductAttributeGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeGroupRepository::class)]
#[ORM\Table(name: 'shop_product_attribute_group')]
#[ORM\UniqueConstraint(name: 'uniq_shop_attr_group_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_shop_attr_group_external_category', columns: ['external_category_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductAttributeGroup
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    /** YML/feed category id this attribute set belongs to. */
    #[ORM\Column(nullable: true)]
    private ?int $external_category_id = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(mappedBy: 'group', targetEntity: ProductAttribute::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $attributes;

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
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

    public function getExternalCategoryId(): ?int
    {
        return $this->external_category_id;
    }

    public function setExternalCategoryId(?int $externalCategoryId): static
    {
        $this->external_category_id = $externalCategoryId;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = max(0, $position);

        return $this;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(ProductAttribute $attribute): static
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setGroup($this);
        }

        return $this;
    }

    public function removeAttribute(ProductAttribute $attribute): static
    {
        if ($this->attributes->removeElement($attribute) && $attribute->getGroup() === $this) {
            $attribute->setGroup(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Attribute group';
    }
}
