<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductImportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductImportRepository::class)]
#[ORM\Table(name: 'shop_product_import')]
#[ORM\UniqueConstraint(name: 'uniq_shop_product_import_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class ProductImport
{
    use TimeStampAbleTrait;

    public const CODE_SIVITEK = 'sivitek';
    public const CODE_LUGI = 'lugi';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 64)]
    private string $code = '';

    #[ORM\Column(length: 32, enumType: ProductImportMode::class, options: ['default' => 'add_update'])]
    private ProductImportMode $mode = ProductImportMode::AddUpdate;

    #[ORM\Column(length: 512)]
    private string $file_path = '';

    /** @var list<string> SKUs that must stay disabled after import */
    #[ORM\Column(type: 'json')]
    private array $disabled_skus = [];

    /** Default category external_id from shop_category (supplier/feed id), not internal PK. */
    #[ORM\Column(nullable: true)]
    private ?int $default_category_external_id = null;

    /** When true, disable categories listed in disabled_category_slugs after import. */
    #[ORM\Column(options: ['default' => false])]
    private bool $disable_categories_after_import = false;

    /** @var list<string> Category slugs to disable after import */
    #[ORM\Column(type: 'json')]
    private array $disabled_category_slugs = [];

    /** Markup percent applied to imported prices (e.g. 30 = +30%). */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0'])]
    private string $markup_percent = '0';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $last_run_at = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $last_result = null;

    /** @var Collection<int, ProductImportRun> */
    #[ORM\OneToMany(mappedBy: 'productImport', targetEntity: ProductImportRun::class, cascade: ['persist'], orphanRemoval: false)]
    #[ORM\OrderBy(['started_at' => 'DESC'])]
    private Collection $runs;

    public function __construct()
    {
        $this->runs = new ArrayCollection();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = trim(mb_strtolower($code));

        return $this;
    }

    public function getMode(): ProductImportMode
    {
        return $this->mode;
    }

    public function getModeLabel(): string
    {
        return $this->mode->label();
    }

    public function setMode(ProductImportMode|string $mode): static
    {
        if (\is_string($mode)) {
            $mode = ProductImportMode::from($mode);
        }
        $this->mode = $mode;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->file_path;
    }

    public function setFilePath(string $filePath): static
    {
        $this->file_path = trim($filePath);

        return $this;
    }

    /** @return list<string> */
    public function getDisabledSkus(): array
    {
        return $this->disabled_skus;
    }

    /** @param list<string>|array<int|string, mixed> $disabledSkus */
    public function setDisabledSkus(array $disabledSkus): static
    {
        $normalized = [];
        foreach ($disabledSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $normalized[] = $sku;
        }
        $this->disabled_skus = array_values(array_unique($normalized));

        return $this;
    }

    public function getDisabledSkusText(): string
    {
        return implode("\n", $this->disabled_skus);
    }

    public function setDisabledSkusText(?string $text): static
    {
        $lines = preg_split('/\R+/', (string) $text) ?: [];
        $this->setDisabledSkus($lines);

        return $this;
    }

    public function getDefaultCategoryExternalId(): ?int
    {
        return $this->default_category_external_id;
    }

    public function setDefaultCategoryExternalId(?int $defaultCategoryExternalId): static
    {
        $this->default_category_external_id = $defaultCategoryExternalId;

        return $this;
    }

    public function isDisableCategoriesAfterImport(): bool
    {
        return $this->disable_categories_after_import;
    }

    public function setDisableCategoriesAfterImport(bool $disableCategoriesAfterImport): static
    {
        $this->disable_categories_after_import = $disableCategoriesAfterImport;

        return $this;
    }

    /** @return list<string> */
    public function getDisabledCategorySlugs(): array
    {
        return $this->disabled_category_slugs;
    }

    /** @param list<string>|array<int|string, mixed> $disabledCategorySlugs */
    public function setDisabledCategorySlugs(array $disabledCategorySlugs): static
    {
        $normalized = [];
        foreach ($disabledCategorySlugs as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }
            $normalized[] = $slug;
        }
        $this->disabled_category_slugs = array_values(array_unique($normalized));

        return $this;
    }

    public function getDisabledCategorySlugsText(): string
    {
        return implode("\n", $this->disabled_category_slugs);
    }

    public function setDisabledCategorySlugsText(?string $text): static
    {
        $lines = preg_split('/\R+/', (string) $text) ?: [];
        $this->setDisabledCategorySlugs($lines);

        return $this;
    }

    public function getMarkupPercent(): float
    {
        return (float) $this->markup_percent;
    }

    public function setMarkupPercent(float|int|string|null $markupPercent): static
    {
        $value = (float) ($markupPercent ?? 0);
        if ($value < 0) {
            $value = 0;
        }
        $this->markup_percent = number_format($value, 2, '.', '');

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getLastRunAt(): ?\DateTimeImmutable
    {
        return $this->last_run_at;
    }

    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): static
    {
        $this->last_run_at = $lastRunAt;

        return $this;
    }

    public function getLastResult(): ?string
    {
        return $this->last_result;
    }

    public function setLastResult(?string $lastResult): static
    {
        $this->last_result = $lastResult !== null ? trim($lastResult) : null;

        return $this;
    }

    /** @return Collection<int, ProductImportRun> */
    public function getRuns(): Collection
    {
        return $this->runs;
    }

    public function addRun(ProductImportRun $run): static
    {
        if (!$this->runs->contains($run)) {
            $this->runs->add($run);
            $run->setProductImport($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Product import';
    }
}
