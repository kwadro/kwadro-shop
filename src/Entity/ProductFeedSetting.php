<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductFeedSettingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductFeedSettingRepository::class)]
#[ORM\Table(name: 'shop_product_feed_setting')]
#[ORM\UniqueConstraint(name: 'uniq_shop_product_feed_setting_site', columns: ['site_id'])]
#[UniqueEntity(fields: ['site'])]
#[ORM\HasLifecycleCallbacks]
class ProductFeedSetting
{
    use TimeStampAbleTrait;

    public const FORMAT_CSV = 'csv';
    public const FORMAT_TSV = 'tsv';

    public const CONDITION_NEW = 'new';
    public const CONDITION_REFURBISHED = 'refurbished';
    public const CONDITION_USED = 'used';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Site $site = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    /** When set, the public feed URL requires ?token=… */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(length: 8, options: ['default' => self::FORMAT_CSV])]
    private string $format = self::FORMAT_CSV;

    #[ORM\Column(length: 8, options: ['default' => 'ua'])]
    private string $localeCode = 'ua';

    #[ORM\Column(length: 3, options: ['default' => 'UAH'])]
    private string $currency = 'UAH';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $defaultBrand = null;

    #[ORM\Column(length: 32, options: ['default' => self::CONDITION_NEW])]
    private string $itemCondition = self::CONDITION_NEW;

    /** Fallback when a product category has no google_product_category. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultGoogleProductCategory = null;

    /** Fallback when a product category has no fb_product_category. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultFbProductCategory = null;

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $accessToken = $accessToken !== null ? trim($accessToken) : null;
        $this->accessToken = $accessToken !== '' ? $accessToken : null;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $format = strtolower(trim($format));
        $this->format = in_array($format, [self::FORMAT_CSV, self::FORMAT_TSV], true)
            ? $format
            : self::FORMAT_CSV;

        return $this;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $localeCode): static
    {
        $localeCode = strtolower(trim($localeCode));
        $this->localeCode = $localeCode !== '' ? $localeCode : 'ua';

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $currency = strtoupper(trim($currency));
        $this->currency = $currency !== '' ? $currency : 'UAH';

        return $this;
    }

    public function getDefaultBrand(): ?string
    {
        return $this->defaultBrand;
    }

    public function setDefaultBrand(?string $defaultBrand): static
    {
        $defaultBrand = $defaultBrand !== null ? trim($defaultBrand) : null;
        $this->defaultBrand = $defaultBrand !== '' ? mb_substr($defaultBrand, 0, 100) : null;

        return $this;
    }

    public function getItemCondition(): string
    {
        return $this->itemCondition;
    }

    public function setItemCondition(string $itemCondition): static
    {
        $itemCondition = strtolower(trim($itemCondition));
        $allowed = [self::CONDITION_NEW, self::CONDITION_REFURBISHED, self::CONDITION_USED];
        $this->itemCondition = in_array($itemCondition, $allowed, true)
            ? $itemCondition
            : self::CONDITION_NEW;

        return $this;
    }

    public function getDefaultGoogleProductCategory(): ?string
    {
        return $this->defaultGoogleProductCategory;
    }

    public function setDefaultGoogleProductCategory(?string $defaultGoogleProductCategory): static
    {
        $defaultGoogleProductCategory = $defaultGoogleProductCategory !== null
            ? trim($defaultGoogleProductCategory)
            : null;
        $this->defaultGoogleProductCategory = $defaultGoogleProductCategory !== ''
            ? $defaultGoogleProductCategory
            : null;

        return $this;
    }

    public function getDefaultFbProductCategory(): ?string
    {
        return $this->defaultFbProductCategory;
    }

    public function setDefaultFbProductCategory(?string $defaultFbProductCategory): static
    {
        $defaultFbProductCategory = $defaultFbProductCategory !== null
            ? trim($defaultFbProductCategory)
            : null;
        $this->defaultFbProductCategory = $defaultFbProductCategory !== ''
            ? $defaultFbProductCategory
            : null;

        return $this;
    }

    public function __toString(): string
    {
        $domain = $this->site?->getDomain() ?? 'Product feed';

        return 'Product feed · '.$domain;
    }
}
