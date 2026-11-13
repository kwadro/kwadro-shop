<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductAttributeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeRepository::class)]
#[ORM\Table(name: 'shop_product_attribute')]
#[ORM\UniqueConstraint(name: 'uniq_shop_product_attribute_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class ProductAttribute
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

    #[ORM\Column(options: ['default' => true])]
    private bool $show_in_filter = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $use_in_search = false;

    #[ORM\Column(length: 16, enumType: ProductAttributeDataType::class)]
    private ProductAttributeDataType $data_type = ProductAttributeDataType::Varchar;

    /** @var Collection<int, ProductAttributeGroupItem> */
    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: ProductAttributeGroupItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $groupItems;

    public function __construct()
    {
        $this->groupItems = new ArrayCollection();
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

    /** @return Collection<int, ProductAttributeGroupItem> */
    public function getGroupItems(): Collection
    {
        return $this->groupItems;
    }

    /** @return Collection<int, ProductAttributeGroup> */
    public function getGroups(): Collection
    {
        $groups = new ArrayCollection();
        foreach ($this->groupItems as $item) {
            $group = $item->getGroup();
            if ($group instanceof ProductAttributeGroup && !$groups->contains($group)) {
                $groups->add($group);
            }
        }

        return $groups;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Attribute';
    }
}
