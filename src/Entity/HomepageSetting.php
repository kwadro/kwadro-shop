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
    public const DEFAULT_SLIDER_INTERVAL_MS = 5000;
    public const MIN_SLIDER_INTERVAL_MS = 1000;
    public const MAX_SLIDER_INTERVAL_MS = 60000;
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

    #[ORM\Column(options: ['default' => false])]
    private bool $heroSliderAutoplay = false;

    #[ORM\Column(options: ['default' => self::DEFAULT_SLIDER_INTERVAL_MS])]
    #[Assert\Range(min: self::MIN_SLIDER_INTERVAL_MS, max: self::MAX_SLIDER_INTERVAL_MS)]
    private int $heroSliderIntervalMs = self::DEFAULT_SLIDER_INTERVAL_MS;

    #[ORM\Column(options: ['default' => true])]
    private bool $categoriesEnabled = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $categoriesTopTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $categoriesTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $categoriesSubTitle = null;

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

    #[ORM\Column(options: ['default' => false])]
    private bool $categoriesSliderAutoplay = false;

    #[ORM\Column(options: ['default' => self::DEFAULT_SLIDER_INTERVAL_MS])]
    #[Assert\Range(min: self::MIN_SLIDER_INTERVAL_MS, max: self::MAX_SLIDER_INTERVAL_MS)]
    private int $categoriesSliderIntervalMs = self::DEFAULT_SLIDER_INTERVAL_MS;

    #[ORM\Column(options: ['default' => true])]
    private bool $productsEnabled = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productsTopTitle = null;

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

    /**
     * Always shown first in the products block (auto or manual), in this order.
     *
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'shop_homepage_setting_product_top')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderColumn(name: 'position')]
    private Collection $topProducts;

    #[ORM\Column(options: ['default' => false])]
    private bool $productsSliderAutoplay = false;

    #[ORM\Column(options: ['default' => self::DEFAULT_SLIDER_INTERVAL_MS])]
    #[Assert\Range(min: self::MIN_SLIDER_INTERVAL_MS, max: self::MAX_SLIDER_INTERVAL_MS)]
    private int $productsSliderIntervalMs = self::DEFAULT_SLIDER_INTERVAL_MS;

    #[ORM\Column(options: ['default' => true])]
    private bool $articlesEnabled = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $articlesTopTitle = null;

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

    #[ORM\Column(options: ['default' => true])]
    private bool $contactCtaEnabled = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaEyebrow = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contactCtaLead = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaBadge1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaBadge2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaBadge3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaPhoneLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaLink1Label = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $contactCtaLink1Url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaLink2Label = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $contactCtaLink2Url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactCtaLink3Label = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $contactCtaLink3Url = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contactCtaNote = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $promoStripEnabled = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $promoStripDismissible = true;

    #[ORM\Column(options: ['default' => self::DEFAULT_SLIDER_INTERVAL_MS])]
    #[Assert\Range(min: self::MIN_SLIDER_INTERVAL_MS, max: self::MAX_SLIDER_INTERVAL_MS)]
    private int $promoStripIntervalMs = self::DEFAULT_SLIDER_INTERVAL_MS;

    /** @var Collection<int, HomepagePromoStripItem> */
    #[ORM\OneToMany(
        mappedBy: 'homepageSetting',
        targetEntity: HomepagePromoStripItem::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $promoStripItems;

    public function __construct()
    {
        $this->heroSlides = new ArrayCollection();
        $this->categoryItems = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->topProducts = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->promoStripItems = new ArrayCollection();
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

    public function isHeroSliderAutoplay(): bool
    {
        return $this->heroSliderAutoplay;
    }

    public function setHeroSliderAutoplay(bool $heroSliderAutoplay): static
    {
        $this->heroSliderAutoplay = $heroSliderAutoplay;

        return $this;
    }

    public function getHeroSliderIntervalMs(): int
    {
        return $this->heroSliderIntervalMs;
    }

    public function setHeroSliderIntervalMs(?int $heroSliderIntervalMs): static
    {
        $this->heroSliderIntervalMs = self::normalizeSliderInterval($heroSliderIntervalMs);

        return $this;
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

    public function getCategoriesTopTitle(): ?string
    {
        return $this->categoriesTopTitle;
    }

    public function setCategoriesTopTitle(?string $categoriesTopTitle): static
    {
        $this->categoriesTopTitle = self::normalizeText($categoriesTopTitle);

        return $this;
    }

    public function getCategoriesTitle(): ?string
    {
        return $this->categoriesTitle;
    }

    public function setCategoriesTitle(?string $categoriesTitle): static
    {
        $this->categoriesTitle = self::normalizeText($categoriesTitle);

        return $this;
    }

    public function getCategoriesSubTitle(): ?string
    {
        return $this->categoriesSubTitle;
    }

    public function setCategoriesSubTitle(?string $categoriesSubTitle): static
    {
        $this->categoriesSubTitle = self::normalizeText($categoriesSubTitle);

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

    public function isCategoriesSliderAutoplay(): bool
    {
        return $this->categoriesSliderAutoplay;
    }

    public function setCategoriesSliderAutoplay(bool $categoriesSliderAutoplay): static
    {
        $this->categoriesSliderAutoplay = $categoriesSliderAutoplay;

        return $this;
    }

    public function getCategoriesSliderIntervalMs(): int
    {
        return $this->categoriesSliderIntervalMs;
    }

    public function setCategoriesSliderIntervalMs(?int $categoriesSliderIntervalMs): static
    {
        $this->categoriesSliderIntervalMs = self::normalizeSliderInterval($categoriesSliderIntervalMs);

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

    public function getProductsTopTitle(): ?string
    {
        return $this->productsTopTitle;
    }

    public function setProductsTopTitle(?string $productsTopTitle): static
    {
        $this->productsTopTitle = self::normalizeText($productsTopTitle);

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

    /** @return Collection<int, Product> */
    public function getTopProducts(): Collection
    {
        return $this->topProducts;
    }

    public function addTopProduct(Product $product): static
    {
        if (!$this->topProducts->contains($product)) {
            $this->topProducts->add($product);
        }

        return $this;
    }

    public function removeTopProduct(Product $product): static
    {
        $this->topProducts->removeElement($product);

        return $this;
    }

    /**
     * Ordered product ids for fixed leading places (duplicates collapsed).
     *
     * @return list<int>
     */
    public function getTopProductIds(): array
    {
        $ids = [];
        foreach ($this->topProducts as $product) {
            $id = $product->getId();
            if ($id === null || \in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
        }

        return $ids;
    }

    public function isProductsSliderAutoplay(): bool
    {
        return $this->productsSliderAutoplay;
    }

    public function setProductsSliderAutoplay(bool $productsSliderAutoplay): static
    {
        $this->productsSliderAutoplay = $productsSliderAutoplay;

        return $this;
    }

    public function getProductsSliderIntervalMs(): int
    {
        return $this->productsSliderIntervalMs;
    }

    public function setProductsSliderIntervalMs(?int $productsSliderIntervalMs): static
    {
        $this->productsSliderIntervalMs = self::normalizeSliderInterval($productsSliderIntervalMs);

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

    public function getArticlesTopTitle(): ?string
    {
        return $this->articlesTopTitle;
    }

    public function setArticlesTopTitle(?string $articlesTopTitle): static
    {
        $this->articlesTopTitle = self::normalizeText($articlesTopTitle);

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

    public function isContactCtaEnabled(): bool
    {
        return $this->contactCtaEnabled;
    }

    public function setContactCtaEnabled(bool $contactCtaEnabled): static
    {
        $this->contactCtaEnabled = $contactCtaEnabled;

        return $this;
    }

    public function getContactCtaEyebrow(): ?string
    {
        return $this->contactCtaEyebrow;
    }

    public function setContactCtaEyebrow(?string $contactCtaEyebrow): static
    {
        $this->contactCtaEyebrow = self::normalizeText($contactCtaEyebrow);

        return $this;
    }

    public function getContactCtaTitle(): ?string
    {
        return $this->contactCtaTitle;
    }

    public function setContactCtaTitle(?string $contactCtaTitle): static
    {
        $this->contactCtaTitle = self::normalizeText($contactCtaTitle);

        return $this;
    }

    public function getContactCtaLead(): ?string
    {
        return $this->contactCtaLead;
    }

    public function setContactCtaLead(?string $contactCtaLead): static
    {
        $this->contactCtaLead = self::normalizeText($contactCtaLead);

        return $this;
    }

    public function getContactCtaBadge1(): ?string
    {
        return $this->contactCtaBadge1;
    }

    public function setContactCtaBadge1(?string $contactCtaBadge1): static
    {
        $this->contactCtaBadge1 = self::normalizeText($contactCtaBadge1);

        return $this;
    }

    public function getContactCtaBadge2(): ?string
    {
        return $this->contactCtaBadge2;
    }

    public function setContactCtaBadge2(?string $contactCtaBadge2): static
    {
        $this->contactCtaBadge2 = self::normalizeText($contactCtaBadge2);

        return $this;
    }

    public function getContactCtaBadge3(): ?string
    {
        return $this->contactCtaBadge3;
    }

    public function setContactCtaBadge3(?string $contactCtaBadge3): static
    {
        $this->contactCtaBadge3 = self::normalizeText($contactCtaBadge3);

        return $this;
    }

    public function getContactCtaPhoneLabel(): ?string
    {
        return $this->contactCtaPhoneLabel;
    }

    public function setContactCtaPhoneLabel(?string $contactCtaPhoneLabel): static
    {
        $this->contactCtaPhoneLabel = self::normalizeText($contactCtaPhoneLabel);

        return $this;
    }

    public function getContactCtaPhone(): ?string
    {
        return $this->contactCtaPhone;
    }

    public function setContactCtaPhone(?string $contactCtaPhone): static
    {
        $this->contactCtaPhone = self::normalizeText($contactCtaPhone);

        return $this;
    }

    public function getContactCtaEmail(): ?string
    {
        return $this->contactCtaEmail;
    }

    public function setContactCtaEmail(?string $contactCtaEmail): static
    {
        $this->contactCtaEmail = self::normalizeText($contactCtaEmail);

        return $this;
    }

    public function getContactCtaLink1Label(): ?string
    {
        return $this->contactCtaLink1Label;
    }

    public function setContactCtaLink1Label(?string $contactCtaLink1Label): static
    {
        $this->contactCtaLink1Label = self::normalizeText($contactCtaLink1Label);

        return $this;
    }

    public function getContactCtaLink1Url(): ?string
    {
        return $this->contactCtaLink1Url;
    }

    public function setContactCtaLink1Url(?string $contactCtaLink1Url): static
    {
        $this->contactCtaLink1Url = self::normalizeText($contactCtaLink1Url);

        return $this;
    }

    public function getContactCtaLink2Label(): ?string
    {
        return $this->contactCtaLink2Label;
    }

    public function setContactCtaLink2Label(?string $contactCtaLink2Label): static
    {
        $this->contactCtaLink2Label = self::normalizeText($contactCtaLink2Label);

        return $this;
    }

    public function getContactCtaLink2Url(): ?string
    {
        return $this->contactCtaLink2Url;
    }

    public function setContactCtaLink2Url(?string $contactCtaLink2Url): static
    {
        $this->contactCtaLink2Url = self::normalizeText($contactCtaLink2Url);

        return $this;
    }

    public function getContactCtaLink3Label(): ?string
    {
        return $this->contactCtaLink3Label;
    }

    public function setContactCtaLink3Label(?string $contactCtaLink3Label): static
    {
        $this->contactCtaLink3Label = self::normalizeText($contactCtaLink3Label);

        return $this;
    }

    public function getContactCtaLink3Url(): ?string
    {
        return $this->contactCtaLink3Url;
    }

    public function setContactCtaLink3Url(?string $contactCtaLink3Url): static
    {
        $this->contactCtaLink3Url = self::normalizeText($contactCtaLink3Url);

        return $this;
    }

    public function getContactCtaNote(): ?string
    {
        return $this->contactCtaNote;
    }

    public function setContactCtaNote(?string $contactCtaNote): static
    {
        $this->contactCtaNote = self::normalizeText($contactCtaNote);

        return $this;
    }

    public function isPromoStripEnabled(): bool
    {
        return $this->promoStripEnabled;
    }

    public function setPromoStripEnabled(bool $promoStripEnabled): static
    {
        $this->promoStripEnabled = $promoStripEnabled;

        return $this;
    }

    public function isPromoStripDismissible(): bool
    {
        return $this->promoStripDismissible;
    }

    public function setPromoStripDismissible(bool $promoStripDismissible): static
    {
        $this->promoStripDismissible = $promoStripDismissible;

        return $this;
    }

    public function getPromoStripIntervalMs(): int
    {
        return $this->promoStripIntervalMs;
    }

    public function setPromoStripIntervalMs(?int $promoStripIntervalMs): static
    {
        $this->promoStripIntervalMs = self::normalizeSliderInterval($promoStripIntervalMs);

        return $this;
    }

    /** @return Collection<int, HomepagePromoStripItem> */
    public function getPromoStripItems(): Collection
    {
        return $this->promoStripItems;
    }

    public function addPromoStripItem(HomepagePromoStripItem $item): static
    {
        if (!$this->promoStripItems->contains($item)) {
            $this->promoStripItems->add($item);
            $item->setHomepageSetting($this);
        }

        return $this;
    }

    public function removePromoStripItem(HomepagePromoStripItem $item): static
    {
        if ($this->promoStripItems->removeElement($item) && $item->getHomepageSetting() === $this) {
            $item->setHomepageSetting(null);
        }

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

    private static function normalizeSliderInterval(?int $intervalMs): int
    {
        return max(
            self::MIN_SLIDER_INTERVAL_MS,
            min(self::MAX_SLIDER_INTERVAL_MS, $intervalMs ?? self::DEFAULT_SLIDER_INTERVAL_MS),
        );
    }
}
