<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Form\Model\ProductGalleryItem;
use App\Repository\ProductRepository;
use App\Routing\ShopRoutes;
use App\Service\Product\ProductImagePath;
use App\Service\Product\ProductOgImagePath;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[Assert\Regex(
        pattern: '#^(?:'.ShopRoutes::SLUG_REQUIREMENTS.')?$#',
        message: 'Slug may contain only letters, digits, hyphen and underscore.',
    )]
    private string $slug = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

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

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $meta_title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $meta_description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $og_title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $og_description = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $og_type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $og_image = null;

    /** Filename of background-removed product image (stored under /uploads/products/…). */
    #[ORM\Column(name: 'clean_image', length: 255, nullable: true)]
    private ?string $cleanImage = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $features = [];

    /** @var list<array{thumb: string, full: string, alt: string}> */
    #[ORM\Column(type: 'json')]
    private array $gallery = [];

    /**
     * Facebook / social post variants for this product.
     *
     * @var list<array{id: string, text: string, createdAt: string, updatedAt: string|null}>
     */
    #[ORM\Column(name: 'social_posts', type: 'json')]
    private array $socialPosts = [];

    /** @var Collection<int, ProductGalleryItem>|null */
    private ?Collection $galleryItems = null;

    /** @var Collection<int, ProductOffer> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductOffer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['price' => 'ASC'])]
    private Collection $offers;

    /** @var Collection<int, ProductAttributeValue> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttributeValue::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributeValues;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->attributeValues = new ArrayCollection();
        $this->galleryItems = new ArrayCollection();
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

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $model = $model !== null ? trim($model) : null;
        $this->model = $model !== '' ? $model : null;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $brand = $brand !== null ? trim($brand) : null;
        $this->brand = $brand !== '' ? $brand : null;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $color = $color !== null ? trim($color) : null;
        $this->color = $color !== '' ? $color : null;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $type = $type !== null ? trim($type) : null;
        $this->type = $type !== '' ? $type : null;

        return $this;
    }

    /**
     * Builds product name as "Model Brand".
     */
    public function rebuildNameFromModelBrand(): static
    {
        $parts = array_values(array_filter([
            $this->model !== null ? trim($this->model) : '',
            $this->brand !== null ? trim($this->brand) : '',
        ], static fn (string $part): bool => $part !== ''));

        if ($parts !== []) {
            $this->name = implode(' ', $parts);
        }

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
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = trim($value, '-_');

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $title = $title !== null ? trim($title) : null;
        $this->title = $title !== '' ? $title : null;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->meta_title;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $metaTitle = $metaTitle !== null ? trim($metaTitle) : null;
        $this->meta_title = $metaTitle !== '' ? $metaTitle : null;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->meta_description;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $metaDescription = $metaDescription !== null ? trim($metaDescription) : null;
        $this->meta_description = $metaDescription !== '' ? $metaDescription : null;

        return $this;
    }

    public function getOgTitle(): ?string
    {
        return $this->og_title;
    }

    public function setOgTitle(?string $ogTitle): static
    {
        $ogTitle = $ogTitle !== null ? trim($ogTitle) : null;
        $this->og_title = $ogTitle !== '' ? $ogTitle : null;

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->og_description;
    }

    public function setOgDescription(?string $ogDescription): static
    {
        $ogDescription = $ogDescription !== null ? trim($ogDescription) : null;
        $this->og_description = $ogDescription !== '' ? $ogDescription : null;

        return $this;
    }

    public function getOgType(): ?string
    {
        return $this->og_type;
    }

    public function setOgType(?string $ogType): static
    {
        $ogType = $ogType !== null ? trim($ogType) : null;
        $this->og_type = $ogType !== '' ? $ogType : null;

        return $this;
    }

    public function getOgImage(): ?string
    {
        if ($this->og_image === null || $this->og_image === '') {
            return null;
        }

        // Relative to /uploads/images/og-images for EasyAdmin ImageField.
        return ProductOgImagePath::relativePath($this->og_image) ?: $this->og_image;
    }

    public function setOgImage(?string $ogImage): static
    {
        $ogImage = $ogImage !== null ? trim($ogImage) : null;
        if ($ogImage === null || $ogImage === '') {
            $this->og_image = null;

            return $this;
        }

        $this->og_image = ProductOgImagePath::relativePath($ogImage) ?: null;

        return $this;
    }

    public function relocateOgImageFile(string $projectDir): void
    {
        $relative = $this->getOgImage();
        if ($relative === null) {
            return;
        }

        ProductOgImagePath::ensureStored($projectDir, $relative);
    }

    /**
     * Admin ImageField expects a path relative to /uploads/products.
     */
    public function getCleanImage(): ?string
    {
        if ($this->cleanImage === null || $this->cleanImage === '') {
            return null;
        }

        return ProductImagePath::relativePath($this->cleanImage, $this->getImageSupplierSlug());
    }

    public function getCleanImageFilename(): ?string
    {
        return $this->cleanImage !== null && $this->cleanImage !== '' ? $this->cleanImage : null;
    }

    public function setCleanImage(?string $cleanImage): static
    {
        $filename = ProductImagePath::filename($cleanImage);
        $this->cleanImage = $filename !== '' ? $filename : null;

        return $this;
    }

    public function getCleanImageWebPath(): string
    {
        return ProductImagePath::webPath($this->cleanImage, $this->getImageSupplierSlug());
    }

    public function relocateCleanImageFile(string $projectDir): void
    {
        $filename = $this->getCleanImageFilename();
        if ($filename === null) {
            return;
        }
        ProductImagePath::ensureStored($projectDir, $filename, $this->getImageSupplierSlug());
    }

    /**
     * Supplier slug used in product image URLs (/uploads/products/{slug}/…).
     */
    public function getImageSupplierSlug(): string
    {
        foreach ([$this->getLowestAvailableOffer(), $this->getLowestOffer()] as $offer) {
            if (!$offer instanceof ProductOffer) {
                continue;
            }
            $slug = $offer->getSupplier()?->getSlug();
            if ($slug !== null && trim($slug) !== '') {
                return trim($slug);
            }
        }

        foreach ($this->offers as $offer) {
            $slug = $offer->getSupplier()?->getSlug();
            if ($slug !== null && trim($slug) !== '') {
                return trim($slug);
            }
        }

        return 'mahazyn-kvadro';
    }

    /** Unmapped admin helper for clean-image JS config (EasyAdmin form only). */
    public function getCleanImageTools(): ?string
    {
        return null;
    }

    public function setCleanImageTools(?string $cleanImageTools): static
    {
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

    /** @param list<array{thumb?: string, full?: string, alt?: string}|string> $gallery */
    public function setGallery(array $gallery): static
    {
        $normalized = [];
        foreach ($gallery as $item) {
            if (\is_string($item)) {
                $filename = ProductImagePath::filename($item);
                if ($filename === '') {
                    continue;
                }
                $normalized[] = [
                    'thumb' => $filename,
                    'full' => $filename,
                    'alt' => $this->name,
                ];
                continue;
            }

            if (!\is_array($item)) {
                continue;
            }

            $full = ProductImagePath::filename((string) ($item['full'] ?? ''));
            if ($full === '') {
                $full = ProductImagePath::filename((string) ($item['thumb'] ?? ''));
            }
            if ($full === '') {
                continue;
            }

            $normalized[] = [
                'thumb' => ProductImagePath::filename((string) ($item['thumb'] ?? $full)) ?: $full,
                'full' => $full,
                'alt' => trim((string) ($item['alt'] ?? $this->name)),
            ];
        }

        $this->gallery = $normalized;

        return $this;
    }

    /** @return Collection<int, ProductGalleryItem> */
    public function getGalleryItems(): Collection
    {
        if ($this->galleryItems === null) {
            $this->hydrateGalleryItems();
        }

        return $this->galleryItems;
    }

    /** @param Collection<int, ProductGalleryItem>|list<ProductGalleryItem> $galleryItems */
    public function setGalleryItems(Collection|array $galleryItems): static
    {
        $this->galleryItems = $galleryItems instanceof Collection
            ? $galleryItems
            : new ArrayCollection(array_values($galleryItems));

        return $this;
    }

    #[ORM\PostLoad]
    public function hydrateGalleryItems(): void
    {
        $items = new ArrayCollection();
        foreach ($this->gallery as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $item = new ProductGalleryItem();
            $item->path = $this->adminGalleryRelativePath((string) ($row['full'] ?? $row['thumb'] ?? ''));
            $item->alt = trim((string) ($row['alt'] ?? $this->name));
            $items->add($item);
        }
        $this->galleryItems = $items;
    }

    /**
     * Persist uploaded gallery files into the flat products upload dir, then rebuild JSON gallery.
     */
    public function syncGalleryFromGalleryItems(string $projectDir): void
    {
        $uploadDir = rtrim($projectDir, '/').'/public/uploads/products';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Cannot create product upload directory.');
        }

        $items = [];
        foreach ($this->getGalleryItems() as $item) {
            if (!$item instanceof ProductGalleryItem) {
                continue;
            }

            $filename = '';
            if ($item->file instanceof UploadedFile && $item->file->isValid()) {
                $ext = $item->file->guessExtension() ?: 'jpg';
                $filename = 'gallery-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$ext;
                $item->file->move($uploadDir, $filename);
                $item->path = $filename;
                $item->file = null;
            } else {
                $filename = ProductImagePath::filename((string) ($item->path ?? ''));
            }

            if ($filename === '') {
                continue;
            }

            $items[] = [
                'thumb' => $filename,
                'full' => $filename,
                'alt' => trim($item->alt) !== '' ? trim($item->alt) : $this->name,
            ];
        }

        $this->setGallery($items);
        $this->hydrateGalleryItems();
    }

    /**
     * @return list<array{id: string, text: string, createdAt: string, updatedAt: string|null}>
     */
    public function getSocialPosts(): array
    {
        return $this->socialPosts;
    }

    /**
     * @param list<array{id?: string, text?: string, createdAt?: string, updatedAt?: string|null}> $socialPosts
     */
    public function setSocialPosts(array $socialPosts): static
    {
        $normalized = [];
        foreach ($socialPosts as $post) {
            if (!\is_array($post)) {
                continue;
            }
            $text = trim((string) ($post['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $id = trim((string) ($post['id'] ?? ''));
            if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]{6,40}$/', $id)) {
                $id = bin2hex(random_bytes(8));
            }
            $createdAt = isset($post['createdAt']) && \is_string($post['createdAt']) && $post['createdAt'] !== ''
                ? $post['createdAt']
                : (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $updatedAt = isset($post['updatedAt']) && \is_string($post['updatedAt']) ? $post['updatedAt'] : null;
            $normalized[] = [
                'id' => $id,
                'text' => $text,
                'createdAt' => $createdAt,
                'updatedAt' => $updatedAt,
            ];
        }
        $this->socialPosts = $normalized;

        return $this;
    }

    /** @return array{id: string, text: string, createdAt: string, updatedAt: string|null} */
    public function addSocialPost(string $text): array
    {
        $text = trim($text);
        $post = [
            'id' => bin2hex(random_bytes(8)),
            'text' => $text,
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'updatedAt' => null,
        ];
        $this->socialPosts[] = $post;

        return $post;
    }

    public function updateSocialPost(string $id, string $text): bool
    {
        foreach ($this->socialPosts as $i => $post) {
            if (($post['id'] ?? '') !== $id) {
                continue;
            }
            $this->socialPosts[$i]['text'] = trim($text);
            $this->socialPosts[$i]['updatedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

            return true;
        }

        return false;
    }

    public function removeSocialPost(string $id): bool
    {
        $before = \count($this->socialPosts);
        $this->socialPosts = array_values(array_filter(
            $this->socialPosts,
            static fn (array $post): bool => ($post['id'] ?? '') !== $id,
        ));

        return \count($this->socialPosts) < $before;
    }

    /**
     * Relocate freshly uploaded flat files into sharded directories.
     */
    public function relocateGalleryFiles(string $projectDir): void
    {
        $supplier = $this->getImageSupplierSlug();
        foreach ($this->gallery as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $filename = ProductImagePath::filename((string) ($item['full'] ?? ''));
            if ($filename === '') {
                continue;
            }
            ProductImagePath::ensureStored($projectDir, $filename, $supplier);
        }
    }

    /** @return list<array{thumb: string, full: string, alt: string}> */
    public function getGalleryForCatalog(): array
    {
        $supplier = $this->getImageSupplierSlug();
        $resolved = [];
        foreach ($this->gallery as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $filename = ProductImagePath::filename((string) ($item['full'] ?? $item['thumb'] ?? ''));
            if ($filename === '') {
                continue;
            }
            $webPath = ProductImagePath::webPath($filename, $supplier);
            $resolved[] = [
                'thumb' => $webPath,
                'full' => $webPath,
                'alt' => trim((string) ($item['alt'] ?? $this->name)),
            ];
        }

        return $resolved;
    }

    private function normalizeGalleryFormValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $filename = ProductImagePath::filename($value);
        if ($filename === '') {
            return null;
        }

        // Keep relative shard path for EasyAdmin preview (basePath=/uploads/products).
        return ProductImagePath::relativePath($filename, $this->getImageSupplierSlug());
    }

    private function adminGalleryRelativePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $filename = ProductImagePath::filename($path);
        if ($filename === '') {
            return null;
        }

        return ProductImagePath::relativePath($filename, $this->getImageSupplierSlug());
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

    /** @return Collection<int, ProductAttributeValue> */
    public function getAttributeValues(): Collection
    {
        return $this->attributeValues;
    }

    public function addAttributeValue(ProductAttributeValue $value): static
    {
        if (!$this->attributeValues->contains($value)) {
            $this->attributeValues->add($value);
            $value->setProduct($this);
        }

        return $this;
    }

    public function removeAttributeValue(ProductAttributeValue $value): static
    {
        if ($this->attributeValues->removeElement($value) && $value->getProduct() === $this) {
            $value->setProduct(null);
        }

        return $this;
    }

    /**
     * @return list<array{name: string, value: string, slug: string, showInFilter: bool, group: string|null}>
     */
    public function getAttributesCatalogArray(): array
    {
        $items = [];
        foreach ($this->attributeValues as $value) {
            $attribute = $value->getAttribute();
            $display = $value->getDisplayValue();
            if ($attribute === null || $display === '') {
                continue;
            }
            $items[] = [
                'name' => $attribute->getName(),
                'value' => $display,
                'slug' => $attribute->getSlug(),
                'filterKey' => $attribute->getFilterKey(),
                'showInFilter' => $attribute->isShowInFilter(),
                'group' => $attribute->getGroup()?->getName(),
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $items;
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
        return $this->enabled && $this->in_stock && $this->getLowestAvailableOffer() !== null;
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
                'slug' => $supplier->getSlug(),
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
            'model' => $this->model,
            'brand' => $this->brand,
            'color' => $this->color,
            'type' => $this->type,
            'category' => $this->getCategoriesLabel(),
            'categories' => array_values(array_filter(array_map(
                static fn (Category $category): ?array => ($category->isDefault() || !$category->isEnabled())
                    ? null
                    : [
                        'id' => $category->getId(),
                        'name' => $category->getName(),
                        'slug' => $category->getSlug(),
                        'level' => $category->getLevel(),
                    ],
                $this->categories->toArray(),
            ))),
            'hasPrice' => $hasPrice,
            'price' => $price,
            'weight' => $this->getWeight(),
            'packageHeight' => $this->getPackageHeight(),
            'packageWidth' => $this->getPackageWidth(),
            'packageLength' => $this->getPackageLength(),
            'oldPrice' => $oldPrice,
            'discountPercent' => $hasPrice ? ($selectedOffer['discountPercent'] ?? null) : null,
            'productInStock' => $this->in_stock,
            'inStock' => $this->enabled && $this->in_stock && $offerQty > 0,
            'enabled' => $this->enabled,
            'stockQty' => $offerQty,
            'badge' => $this->badge,
            'shortDescription' => $this->short_description ?? '',
            'description' => $this->description ?? '',
            'title' => $this->title,
            'metaTitle' => $this->meta_title,
            'metaDescription' => $this->meta_description,
            'ogTitle' => $this->og_title,
            'ogDescription' => $this->og_description,
            'ogType' => $this->og_type,
            'ogImage' => $this->og_image,
            'cleanImage' => $this->getCleanImageWebPath() ?: null,
            'features' => $this->features,
            'attributes' => $this->getAttributesCatalogArray(),
            'gallery' => $this->getGalleryForCatalog(),
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
