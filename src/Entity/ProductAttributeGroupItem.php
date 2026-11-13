<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductAttributeGroupItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Membership of a global attribute in an attribute group (position is per group).
 */
#[ORM\Entity(repositoryClass: ProductAttributeGroupItemRepository::class)]
#[ORM\Table(name: 'shop_product_attribute_group_item')]
#[ORM\UniqueConstraint(name: 'uniq_shop_attr_group_item', columns: ['group_id', 'attribute_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductAttributeGroupItem
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductAttributeGroup::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductAttributeGroup $group = null;

    #[ORM\ManyToOne(targetEntity: ProductAttribute::class, inversedBy: 'groupItems')]
    #[ORM\JoinColumn(name: 'attribute_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductAttribute $attribute = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): ?ProductAttributeGroup
    {
        return $this->group;
    }

    public function setGroup(?ProductAttributeGroup $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getAttribute(): ?ProductAttribute
    {
        return $this->attribute;
    }

    public function setAttribute(?ProductAttribute $attribute): static
    {
        $this->attribute = $attribute;

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

    public function __toString(): string
    {
        $name = $this->attribute?->getName() ?? '';

        return $name !== '' ? $name : 'Attribute group item';
    }
}
