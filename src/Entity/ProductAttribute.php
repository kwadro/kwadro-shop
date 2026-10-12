<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductAttributeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeRepository::class)]
#[ORM\Table(name: 'shop_product_attribute')]
#[ORM\UniqueConstraint(name: 'uniq_shop_product_attribute_slug', columns: ['group_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class ProductAttribute
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductAttributeGroup::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductAttributeGroup $group = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $show_in_filter = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $use_in_search = false;

    #[ORM\Column(length: 16, enumType: ProductAttributeDataType::class)]
    private ProductAttributeDataType $data_type = ProductAttributeDataType::Varchar;

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

    /** Query-string key used in catalog filters. */
    public function getFilterKey(): string
    {
        return 'pa_'.$this->slug;
    }

    public function isShowInFilter(): bool
    {
        return $this->show_in_filter;
    }

    public function setShowInFilter(bool $showInFilter): static
    {
        $this->show_in_filter = $showInFilter;

        return $this;
    }

    public function isUseInSearch(): bool
    {
        return $this->use_in_search;
    }

    public function setUseInSearch(bool $useInSearch): static
    {
        $this->use_in_search = $useInSearch;

        return $this;
    }

    public function getDataType(): ProductAttributeDataType
    {
        return $this->data_type;
    }

    public function setDataType(ProductAttributeDataType $dataType): static
    {
        $this->data_type = $dataType;

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
        return $this->name !== '' ? $this->name : 'Attribute';
    }
}
