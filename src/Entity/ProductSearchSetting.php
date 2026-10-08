<?php

namespace App\Entity;

use App\Repository\ProductSearchSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductSearchSettingRepository::class)]
#[ORM\Table(name: 'shop_product_search_setting')]
#[ORM\UniqueConstraint(name: 'uniq_shop_product_search_setting_site', columns: ['site_id'])]
class ProductSearchSetting
{
    public const FIELD_NAME = 'name';
    public const FIELD_MODEL = 'model';
    public const FIELD_SKU = 'sku';
    public const FIELD_BRAND = 'brand';

    /** @var list<string> */
    public const ALLOWED_FIELDS = [
        self::FIELD_NAME,
        self::FIELD_MODEL,
        self::FIELD_SKU,
        self::FIELD_BRAND,
    ];

    /** @var list<string> */
    public const DEFAULT_FIELDS = [
        self::FIELD_NAME,
        self::FIELD_MODEL,
        self::FIELD_SKU,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Site $site = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $search_fields = self::DEFAULT_FIELDS;

    public function __construct()
    {
        $this->search_fields = self::DEFAULT_FIELDS;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSearchFields(): array
    {
        if (!\is_array($this->search_fields) || $this->search_fields === []) {
            return self::DEFAULT_FIELDS;
        }

        return array_values(array_intersect($this->search_fields, self::ALLOWED_FIELDS));
    }

    /**
     * @param list<string>|null $searchFields
     */
    public function setSearchFields(?array $searchFields): static
    {
        if ($searchFields === null || $searchFields === []) {
            $this->search_fields = self::DEFAULT_FIELDS;

            return $this;
        }

        $normalized = array_values(array_intersect($searchFields, self::ALLOWED_FIELDS));
        $this->search_fields = $normalized !== [] ? $normalized : self::DEFAULT_FIELDS;

        return $this;
    }

    public function __toString(): string
    {
        $site = $this->site?->getDomain() ?? '';

        return $site !== '' ? 'Product search: '.$site : 'Product search setting';
    }
}
