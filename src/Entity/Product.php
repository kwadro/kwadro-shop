<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'shop_product')]
#[ORM\UniqueConstraint(name: 'uniq_shop_product_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 64)]
    private string $sku = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'shop_product_category')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['level' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'])]
    private Collection $categories;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 3, options: ['default' => '1.000'])]
    private string $weight = '1.000';

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '10.00'])]
    private string $package_height = '10.00';

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '10.00'])]
    private string $package_width = '10.00';

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '10.00'])]
    private string $package_length = '10.00';

    #[ORM\Column(options: ['default' => false])]
    private bool $in_stock = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $stock_qty = 0;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $badge = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $short_description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $features = [];

    /** @var list<array{thumb: string, full: string, alt: string}> */
    #[ORM\Column(type: 'json')]
    private array $gallery = [];

    private ?string $galleryImage1 = null;

    private ?string $galleryImage2 = null;

    private ?string $galleryImage3 = null;

    private ?string $galleryAlt1 = null;

    private ?string $galleryAlt2 = null;

    private ?string $galleryAlt3 = null;

    /** @var Collection<int, ProductOffer> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductOffer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['price' => 'ASC'])]
    private Collection $offers;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->offers = new ArrayCollection();
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

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = trim($sku);

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

    public function ensureSlug(): static
    {
        if ($this->slug !== '') {
            return $this;
        }

        $base = $this->slugify($this->name !== '' ? $this->name : $this->sku);
        $this->slug = $base !== '' ? $base : 'product';

        return $this;
    }

    private function slugify(string $value): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ye',
            'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'yu',
            'я' => 'ya', 'ы' => 'y', 'э' => 'e', 'ъ' => '',
        ];

        $value = mb_strtolower(trim($value));
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCategoryNames(): array
    {
        $names = [];
        foreach ($this->categories as $category) {
            $name = trim($category->getName());
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function getCategoriesLabel(): string
    {
        return implode(', ', $this->getCategoryNames());
    }

    public function getWeight(): float
    {
        return (float) $this->weight;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = number_format(max(0.001, $weight), 3, '.', '');

        return $this;
    }

    public function getPackageHeight(): float
    {
        return (float) $this->package_height;
    }

    public function setPackageHeight(float $packageHeight): static
    {
        $this->package_height = number_format(max(1.0, $packageHeight), 2, '.', '');

        return $this;
    }

    public function getPackageWidth(): float
    {
        return (float) $this->package_width;
    }

    public function setPackageWidth(float $packageWidth): static
    {
        $this->package_width = number_format(max(1.0, $packageWidth), 2, '.', '');

        return $this;
    }

    public function getPackageLength(): float
    {
        return (float) $this->package_length;
    }

    public function setPackageLength(float $packageLength): static
    {
        $this->package_length = number_format(max(1.0, $packageLength), 2, '.', '');

        return $this;
    }

    public function isInStock(): bool
    {
        return $this->in_stock;
    }

    public function setInStock(bool $inStock): static
    {
        $this->in_stock = $inStock;

        return $this;
    }

    public function getStockQty(): int
    {
        return $this->stock_qty;
    }

    public function setStockQty(int $stockQty): static
    {
        $this->stock_qty = max(0, $stockQty);

        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): static
    {
        $this->badge = $badge !== null ? trim($badge) : null;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->short_description;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->short_description = $shortDescription;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return list<string> */
    public function getFeatures(): array
    {
        return $this->features;
    }

    /** @param list<string> $features */
    public function setFeatures(array $features): static
    {
        $this->features = array_values(array_filter(array_map(
            static fn (mixed $feature): string => trim((string) $feature),
            $features,
        ), static fn (string $feature): bool => $feature !== ''));

        return $this;
    }

    /** @return list<array{thumb: string, full: string, alt: string}> */
    public function getGallery(): array
    {
        return $this->gallery;
    }

    /** @param list<array{thumb?: string, full?: string, alt?: string}> $gallery */
    public function setGallery(array $gallery): static
    {
        $normalized = [];
        foreach ($gallery as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $full = trim((string) ($item['full'] ?? ''));
            if ($full === '') {
                continue;
            }

            $normalized[] = [
                'thumb' => trim((string) ($item['thumb'] ?? $full)),
                'full' => $full,
                'alt' => trim((string) ($item['alt'] ?? $this->name)),
            ];
        }

        $this->gallery = $normalized;

        return $this;
    }

    public function getGalleryImage1(): ?string
    {
        return $this->galleryImage1;
    }

    public function setGalleryImage1(?string $galleryImage1): static
    {
        $this->galleryImage1 = $galleryImage1 !== null && trim($galleryImage1) !== '' ? trim($galleryImage1) : null;

        return $this;
    }

    public function getGalleryImage2(): ?string
    {
        return $this->galleryImage2;
    }

    public function setGalleryImage2(?string $galleryImage2): static
    {
        $this->galleryImage2 = $galleryImage2 !== null && trim($galleryImage2) !== '' ? trim($galleryImage2) : null;

        return $this;
    }

    public function getGalleryImage3(): ?string
    {
        return $this->galleryImage3;
    }

    public function setGalleryImage3(?string $galleryImage3): static
    {
        $this->galleryImage3 = $galleryImage3 !== null && trim($galleryImage3) !== '' ? trim($galleryImage3) : null;

        return $this;
    }

    public function getGalleryAlt1(): ?string
    {
        return $this->galleryAlt1;
    }

    public function setGalleryAlt1(?string $galleryAlt1): static
    {
        $this->galleryAlt1 = $galleryAlt1 !== null ? trim($galleryAlt1) : null;

        return $this;
    }

    public function getGalleryAlt2(): ?string
    {
        return $this->galleryAlt2;
    }

    public function setGalleryAlt2(?string $galleryAlt2): static
    {
        $this->galleryAlt2 = $galleryAlt2 !== null ? trim($galleryAlt2) : null;

        return $this;
    }

    public function getGalleryAlt3(): ?string
    {
        return $this->galleryAlt3;
    }

    public function setGalleryAlt3(?string $galleryAlt3): static
    {
        $this->galleryAlt3 = $galleryAlt3 !== null ? trim($galleryAlt3) : null;

        return $this;
    }

    #[ORM\PostLoad]
    public function hydrateGalleryFormFields(): void
    {
        $this->galleryImage1 = null;
        $this->galleryImage2 = null;
        $this->galleryImage3 = null;
        $this->galleryAlt1 = null;
        $this->galleryAlt2 = null;
        $this->galleryAlt3 = null;

        if (isset($this->gallery[0]) && \is_array($this->gallery[0])) {
            $this->galleryImage1 = $this->extractGalleryFilename((string) ($this->gallery[0]['full'] ?? ''));
            $this->galleryAlt1 = trim((string) ($this->gallery[0]['alt'] ?? ''));
        }

        if (isset($this->gallery[1]) && \is_array($this->gallery[1])) {
            $this->galleryImage2 = $this->extractGalleryFilename((string) ($this->gallery[1]['full'] ?? ''));
            $this->galleryAlt2 = trim((string) ($this->gallery[1]['alt'] ?? ''));
        }

        if (isset($this->gallery[2]) && \is_array($this->gallery[2])) {
            $this->galleryImage3 = $this->extractGalleryFilename((string) ($this->gallery[2]['full'] ?? ''));
            $this->galleryAlt3 = trim((string) ($this->gallery[2]['alt'] ?? ''));
        }
    }

    public function syncGalleryFromFormFields(): void
    {
        $items = [];
        foreach ([
            [$this->galleryImage1, $this->galleryAlt1],
            [$this->galleryImage2, $this->galleryAlt2],
            [$this->galleryImage3, $this->galleryAlt3],
        ] as [$image, $alt]) {
            if (!\is_string($image) || trim($image) === '') {
                continue;
            }

            $full = $this->normalizeGalleryImagePath($image);
            $items[] = [
                'thumb' => $full,
                'full' => $full,
                'alt' => \is_string($alt) && trim($alt) !== '' ? trim($alt) : $this->name,
            ];
        }

        $this->setGallery($items);
    }

    private function extractGalleryFilename(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return basename($path);
    }

    private function normalizeGalleryImagePath(string $image): string
    {
        $image = trim($image);
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, '/uploads/')) {
            return $image;
        }

        return '/uploads/products/' . basename($image);
    }

    /** @return Collection<int, ProductOffer> */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(ProductOffer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setProduct($this);
        }

        return $this;
    }

    public function removeOffer(ProductOffer $offer): static
    {
        if ($this->offers->removeElement($offer) && $offer->getProduct() === $this) {
            $offer->setProduct(null);
        }

        return $this;
    }

    public function getLowestOffer(): ?ProductOffer
    {
        $lowest = null;
        foreach ($this->offers as $offer) {
            if ($lowest === null || $offer->getPrice() < $lowest->getPrice()) {
                $lowest = $offer;
            }
        }

        return $lowest;
    }

    public function getLowestAvailableOffer(): ?ProductOffer
    {
        $lowest = null;
        foreach ($this->offers as $offer) {
            if (!$offer->isInStock()) {
                continue;
            }

            if ($lowest === null || $offer->getPrice() < $lowest->getPrice()) {
                $lowest = $offer;
            }
        }

        return $lowest;
    }

    public function getLowestPrice(): ?float
    {
        $offer = $this->getLowestOffer();

        return $offer?->getPrice();
    }

    public function getLowestOldPrice(): ?float
    {
        $offer = $this->getLowestOffer();
        if ($offer === null) {
            return null;
        }

        $oldPrice = $offer->getOldPrice();

        return $oldPrice !== null && $oldPrice > $offer->getPrice() ? $oldPrice : null;
    }

    public function getDiscountPercent(): ?int
    {
        $price = $this->getLowestPrice();
        $oldPrice = $this->getLowestOldPrice();
        if ($price === null || $oldPrice === null || $oldPrice <= 0) {
            return null;
        }

        return (int) round((1 - ($price / $oldPrice)) * 100);
    }

    public function hasOffers(): bool
    {
        return !$this->offers->isEmpty();
    }

    public function isAvailableForSale(): bool
    {
        return $this->in_stock && $this->getLowestAvailableOffer() !== null;
    }

    public function getAvailableForSale(): bool
    {
        return $this->isAvailableForSale();
    }

    public function getLowestOfferSummary(): string
    {
        $offer = $this->getLowestOffer();
        if ($offer === null) {
            return '';
        }

        $parts = [number_format($offer->getPrice(), 2, '.', ' ') . ' ₴'];
        if ($offer->getOldPrice() !== null && $offer->getOldPrice() > $offer->getPrice()) {
            $parts[] = 'було ' . number_format($offer->getOldPrice(), 2, '.', ' ') . ' ₴';
        }
        if ($offer->getSupplier() !== null) {
            $parts[] = $offer->getSupplier()->getName();
        }

        return implode(' · ', $parts);
    }

    public function getOffersCount(): int
    {
        return $this->offers->count();
    }

    public function getFeaturesText(): string
    {
        return implode("\n", $this->features);
    }

    public function setFeaturesText(?string $text): static
    {
        $lines = preg_split('/\R/u', (string) $text) ?: [];

        return $this->setFeatures($lines);
    }

    /** @return array<string, mixed> */
    public function offerToCatalogArray(ProductOffer $offer, bool $isLowest = false): array
    {
        $price = $offer->getPrice();
        $oldPrice = $offer->getOldPrice();
        $discountPercent = null;
        if ($oldPrice !== null && $oldPrice > $price && $oldPrice > 0) {
            $discountPercent = (int) round((1 - ($price / $oldPrice)) * 100);
        }

        $supplier = $offer->getSupplier();

        return [
            'id' => $offer->getId(),
            'sku' => $offer->getSku(),
            'qty' => $offer->getQty(),
            'inStock' => $offer->isInStock(),
            'price' => $price,
            'oldPrice' => $oldPrice !== null && $oldPrice > $price ? $oldPrice : null,
            'discountPercent' => $discountPercent,
            'supplier' => $supplier !== null ? [
                'id' => $supplier->getId(),
                'name' => $supplier->getName(),
                'description' => $supplier->getDescription() ?? '',
                'phone' => $supplier->getPhone() ?? '',
                'email' => $supplier->getEmail() ?? '',
            ] : null,
            'isLowest' => $isLowest,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getOffersCatalogArray(): array
    {
        $lowest = $this->getLowestAvailableOffer();
        $offers = [];

        foreach ($this->offers as $offer) {
            if (!$offer->isInStock()) {
                continue;
            }

            $offers[] = $this->offerToCatalogArray(
                $offer,
                $lowest !== null && $offer->getId() === $lowest->getId(),
            );
        }

        return $offers;
    }

    /** @return array<string, mixed> */
    public function toCatalogArray(): array
    {
        $offers = $this->getOffersCatalogArray();
        $hasPrice = $offers !== [];
        $selectedOffer = $offers[0] ?? null;
        foreach ($offers as $offer) {
            if (!empty($offer['isLowest'])) {
                $selectedOffer = $offer;
                break;
            }
        }

        $price = $hasPrice ? ($selectedOffer['price'] ?? null) : null;
        $oldPrice = $hasPrice ? ($selectedOffer['oldPrice'] ?? null) : null;
        $offerQty = $hasPrice ? (int) ($selectedOffer['qty'] ?? 0) : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'category' => $this->getCategoriesLabel(),
            'categories' => array_map(
                static fn (Category $category): array => [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'slug' => $category->getSlug(),
                    'level' => $category->getLevel(),
                ],
                $this->categories->toArray(),
            ),
            'hasPrice' => $hasPrice,
            'price' => $price,
            'weight' => $this->getWeight(),
            'packageHeight' => $this->getPackageHeight(),
            'packageWidth' => $this->getPackageWidth(),
            'packageLength' => $this->getPackageLength(),
            'oldPrice' => $oldPrice,
            'discountPercent' => $hasPrice ? ($selectedOffer['discountPercent'] ?? null) : null,
            'productInStock' => $this->in_stock,
            'inStock' => $this->in_stock && $offerQty > 0,
            'stockQty' => $offerQty,
            'badge' => $this->badge,
            'shortDescription' => $this->short_description ?? '',
            'description' => $this->description ?? '',
            'features' => $this->features,
            'gallery' => $this->gallery,
            'offersCount' => \count($offers),
            'offers' => $offers,
            'selectedOfferId' => $selectedOffer['id'] ?? null,
            'supplier' => $selectedOffer['supplier'] ?? null,
        ];
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Product';
    }
}
