<?php

namespace App\Entity;

use App\Repository\HomepageSettingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HomepageSettingRepository::class)]
#[ORM\Table(name: 'shop_homepage_setting')]
#[ORM\UniqueConstraint(name: 'uniq_shop_homepage_setting_site_locale', columns: ['site_id', 'locale_id'])]
#[UniqueEntity(fields: ['site', 'locale'])]
class HomepageSetting
{
    public const SOURCE_LATEST = 'latest';
    public const SOURCE_MANUAL = 'manual';

    /** Desktop: show slider when product count is greater than this value. Also used as "latest" fetch size. */
    public const DEFAULT_PRODUCTS_LIMIT = 5;
    public const DEFAULT_ARTICLES_LIMIT = 3;
    public const DEFAULT_CATEGORIES_PER_ROW = 4;
    public const MAX_PRODUCTS_LIMIT = 48;
    public const MAX_ARTICLES_LIMIT = 12;
    public const MAX_CATEGORIES_PER_ROW = 12;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Site $site = null;

    #[ORM\ManyToOne(targetEntity: Locale::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Locale $locale = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $heroEnabled = true;

    /** @var Collection<int, HomepageHeroSlide> */
    #[ORM\OneToMany(
        mappedBy: 'homepageSetting',
        targetEntity: HomepageHeroSlide::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $heroSlides;

    #[ORM\Column(options: ['default' => true])]
    private bool $categoriesEnabled = true;

    #[ORM\Column(length: 20, options: ['default' => self::SOURCE_LATEST])]
    private string $categoriesSource = self::SOURCE_LATEST;

    /** Desktop columns / slider threshold (slider when count > this value). */
    #[ORM\Column(options: ['default' => self::DEFAULT_CATEGORIES_PER_ROW])]
    #[Assert\Range(min: 1, max: self::MAX_CATEGORIES_PER_ROW)]
    private int $categoriesPerRow = self::DEFAULT_CATEGORIES_PER_ROW;

    /** @var Collection<int, HomepageCategoryItem> */
    #[ORM\OneToMany(
        mappedBy: 'homepageSetting',
        targetEntity: HomepageCategoryItem::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $categoryItems;

    #[ORM\Column(options: ['default' => true])]
    private bool $productsEnabled = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productsTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $productsLead = null;

    #[ORM\Column(length: 20, options: ['default' => self::SOURCE_LATEST])]
    private string $productsSource = self::SOURCE_LATEST;

    #[ORM\Column(options: ['default' => self::DEFAULT_PRODUCTS_LIMIT])]
    #[Assert\Range(min: 1, max: self::MAX_PRODUCTS_LIMIT)]
    private int $productsLimit = self::DEFAULT_PRODUCTS_LIMIT;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'shop_homepage_setting_product')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $products;

    #[ORM\Column(options: ['default' => true])]
    private bool $articlesEnabled = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $articlesTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $articlesLead = null;

    #[ORM\Column(length: 20, options: ['default' => self::SOURCE_LATEST])]
    private string $articlesSource = self::SOURCE_LATEST;

    #[ORM\Column(options: ['default' => self::DEFAULT_ARTICLES_LIMIT])]
    #[Assert\Range(min: 1, max: self::MAX_ARTICLES_LIMIT)]
    private int $articlesLimit = self::DEFAULT_ARTICLES_LIMIT;

    /** @var Collection<int, BlogArticle> */
    #[ORM\ManyToMany(targetEntity: BlogArticle::class)]
    #[ORM\JoinTable(name: 'shop_homepage_setting_article')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $articles;

    public function __construct()
    {
        $this->heroSlides = new ArrayCollection();
        $this->categoryItems = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->articles = new ArrayCollection();
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

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(?Locale $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function isHeroEnabled(): bool
    {
        return $this->heroEnabled;
    }

    public function setHeroEnabled(bool $heroEnabled): static
    {
        $this->heroEnabled = $heroEnabled;

        return $this;
    }

    /** @return Collection<int, HomepageHeroSlide> */
    public function getHeroSlides(): Collection
    {
        return $this->heroSlides;
    }

    public function addHeroSlide(HomepageHeroSlide $heroSlide): static
    {
        if (!$this->heroSlides->contains($heroSlide)) {
            $this->heroSlides->add($heroSlide);
            $heroSlide->setHomepageSetting($this);
        }

        return $this;
    }

    public function removeHeroSlide(HomepageHeroSlide $heroSlide): static
    {
        if ($this->heroSlides->removeElement($heroSlide) && $heroSlide->getHomepageSetting() === $this) {
            $heroSlide->setHomepageSetting(null);
        }

        return $this;
    }

    public function syncHeroSlideImages(string $projectDir): void
    {
        foreach ($this->heroSlides as $slide) {
            if ($slide instanceof HomepageHeroSlide) {
                $slide->syncUploadedImage($projectDir);
            }
        }
    }

    public function isCategoriesEnabled(): bool
    {
        return $this->categoriesEnabled;
    }

    public function setCategoriesEnabled(bool $categoriesEnabled): static
    {
        $this->categoriesEnabled = $categoriesEnabled;

        return $this;
    }

    public function getCategoriesSource(): string
    {
        return $this->categoriesSource;
    }

    public function setCategoriesSource(?string $categoriesSource): static
    {
        $this->categoriesSource = self::normalizeSource($categoriesSource);

        return $this;
    }

    public function getCategoriesPerRow(): int
    {
        return $this->categoriesPerRow;
    }

    public function setCategoriesPerRow(?int $categoriesPerRow): static
    {
        $this->categoriesPerRow = max(
            1,
            min(self::MAX_CATEGORIES_PER_ROW, $categoriesPerRow ?? self::DEFAULT_CATEGORIES_PER_ROW),
        );

        return $this;
    }

    /** @return Collection<int, HomepageCategoryItem> */
    public function getCategoryItems(): Collection
    {
        return $this->categoryItems;
    }

    public function addCategoryItem(HomepageCategoryItem $item): static
    {
        if (!$this->categoryItems->contains($item)) {
            $this->categoryItems->add($item);
            $item->setHomepageSetting($this);
        }

        return $this;
    }

    public function removeCategoryItem(HomepageCategoryItem $item): static
    {
        if ($this->categoryItems->removeElement($item) && $item->getHomepageSetting() === $this) {
            $item->setHomepageSetting(null);
        }

        return $this;
    }

    public function isProductsEnabled(): bool
    {
        return $this->productsEnabled;
    }

    public function setProductsEnabled(bool $productsEnabled): static
    {
        $this->productsEnabled = $productsEnabled;

        return $this;
    }

    public function getProductsTitle(): ?string
    {
        return $this->productsTitle;
    }

    public function setProductsTitle(?string $productsTitle): static
    {
        $this->productsTitle = self::normalizeText($productsTitle);

        return $this;
    }

    public function getProductsLead(): ?string
    {
        return $this->productsLead;
    }

    public function setProductsLead(?string $productsLead): static
    {
        $this->productsLead = self::normalizeText($productsLead);

        return $this;
    }

    public function getProductsSource(): string
    {
        return $this->productsSource;
    }

    public function setProductsSource(?string $productsSource): static
    {
        $this->productsSource = self::normalizeSource($productsSource);

        return $this;
    }

    public function getProductsLimit(): int
    {
        return $this->productsLimit;
    }

    public function setProductsLimit(?int $productsLimit): static
    {
        $this->productsLimit = max(1, min(self::MAX_PRODUCTS_LIMIT, $productsLimit ?? self::DEFAULT_PRODUCTS_LIMIT));

        return $this;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }

    public function isArticlesEnabled(): bool
    {
        return $this->articlesEnabled;
    }

    public function setArticlesEnabled(bool $articlesEnabled): static
    {
        $this->articlesEnabled = $articlesEnabled;

        return $this;
    }

    public function getArticlesTitle(): ?string
    {
        return $this->articlesTitle;
    }

    public function setArticlesTitle(?string $articlesTitle): static
    {
        $this->articlesTitle = self::normalizeText($articlesTitle);

        return $this;
    }

    public function getArticlesLead(): ?string
    {
        return $this->articlesLead;
    }

    public function setArticlesLead(?string $articlesLead): static
    {
        $this->articlesLead = self::normalizeText($articlesLead);

        return $this;
    }

    public function getArticlesSource(): string
    {
        return $this->articlesSource;
    }

    public function setArticlesSource(?string $articlesSource): static
    {
        $this->articlesSource = self::normalizeSource($articlesSource);

        return $this;
    }

    public function getArticlesLimit(): int
    {
        return $this->articlesLimit;
    }

    public function setArticlesLimit(?int $articlesLimit): static
    {
        $this->articlesLimit = max(1, min(self::MAX_ARTICLES_LIMIT, $articlesLimit ?? self::DEFAULT_ARTICLES_LIMIT));

        return $this;
    }

    /** @return Collection<int, BlogArticle> */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(BlogArticle $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }

        return $this;
    }

    public function removeArticle(BlogArticle $article): static
    {
        $this->articles->removeElement($article);

        return $this;
    }

    public function __toString(): string
    {
        $site = $this->site?->getDomain() ?? '';
        $locale = $this->locale?->getCode() ?? '';

        return trim(sprintf('Homepage: %s %s', $site, $locale));
    }

    private static function normalizeText(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }

    private static function normalizeSource(?string $source): string
    {
        return $source === self::SOURCE_MANUAL ? self::SOURCE_MANUAL : self::SOURCE_LATEST;
    }
}
