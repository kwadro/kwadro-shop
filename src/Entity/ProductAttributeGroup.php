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
#[ORM\UniqueConstraint(name: 'uniq_shop_attr_group_category', columns: ['category_id'])]
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

    #[ORM\OneToOne(targetEntity: Category::class, inversedBy: 'attributeGroup')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    /** @var Collection<int, ProductAttributeGroupItem> */
    #[ORM\OneToMany(mappedBy: 'group', targetEntity: ProductAttributeGroupItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
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

    /** @return Collection<int, ProductAttributeGroupItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param iterable<ProductAttributeGroupItem>|null $items
     */
    public function setItems(?iterable $items): static
    {
        $incoming = [];
        foreach ($items ?? [] as $item) {
            if ($item instanceof ProductAttributeGroupItem) {
                $incoming[] = $item;
            }
        }

        foreach ($this->items->toArray() as $existing) {
            if (!\in_array($existing, $incoming, true)) {
                $this->items->removeElement($existing);
                $attribute = $existing->getAttribute();
                if ($attribute instanceof ProductAttribute) {
                    $attribute->getGroupItems()->removeElement($existing);
                }
                $existing->setGroup(null);
                $existing->setAttribute(null);
            }
        }

        foreach ($incoming as $item) {
            if ($this->items->contains($item)) {
                continue;
            }
            $item->setGroup($this);
            $this->items->add($item);
            $attribute = $item->getAttribute();
            if ($attribute instanceof ProductAttribute && !$attribute->getGroupItems()->contains($item)) {
                $attribute->getGroupItems()->add($item);
            }
        }

        return $this;
    }

    /** Attributes linked to this group, ordered by membership position. */
    /** @return list<ProductAttribute> */
    public function getAttributes(): array
    {
        $attributes = [];
        foreach ($this->items as $item) {
            $attribute = $item->getAttribute();
            if ($attribute instanceof ProductAttribute) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    public function hasAttribute(ProductAttribute $attribute): bool
    {
        return $this->findItemByAttribute($attribute) instanceof ProductAttributeGroupItem;
    }

    public function findItemByAttribute(ProductAttribute $attribute): ?ProductAttributeGroupItem
    {
        foreach ($this->items as $item) {
            if ($item->getAttribute() === $attribute) {
                return $item;
            }
        }

        return null;
    }

    public function addAttribute(ProductAttribute $attribute, ?int $position = null): static
    {
        $existing = $this->findItemByAttribute($attribute);
        if ($existing instanceof ProductAttributeGroupItem) {
            // Keep manually deleted memberships out of import updates.
            if ($existing->isDeleted()) {
                return $this;
            }
            if ($position !== null) {
                $existing->setPosition($position);
            }

            return $this;
        }

        $item = (new ProductAttributeGroupItem())
            ->setGroup($this)
            ->setAttribute($attribute)
            ->setPosition($position ?? $this->nextItemPosition());
        $this->items->add($item);
        $attribute->getGroupItems()->add($item);

        return $this;
    }

    public function removeAttribute(ProductAttribute $attribute): static
    {
        foreach ($this->items->toArray() as $item) {
            if ($item->getAttribute() !== $attribute) {
                continue;
            }
            $this->items->removeElement($item);
            $attribute->getGroupItems()->removeElement($item);
            $item->setGroup(null);
            $item->setAttribute(null);
        }

        return $this;
    }

    private function nextItemPosition(): int
    {
        $max = 0;
        foreach ($this->items as $item) {
            $max = max($max, $item->getPosition());
        }

        return $max + 1;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Attribute group';
    }
}
