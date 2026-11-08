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

    /**
     * Slugs whose categories and products were turned off by the last import.
     * Used to turn them back on when a slug leaves the disable list.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $applied_disabled_category_slugs = [];

    /** Markup percent applied to imported prices (e.g. 30 = +30%). */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0'])]
    private string $markup_percent = '0';

    /**
     * Menu-only pin list. Does not change the position written by the import file.
     *
     * @var list<array{category_id: int, position: int}>
     */
    #[ORM\Column(type: 'json')]
    private array $menu_top_categories = [];

    /**
     * Menu-only parent overrides. The category row itself keeps the import parent.
     *
     * @var list<array{category_id: int, parent_id: int}>
     */
    #[ORM\Column(type: 'json')]
    private array $menu_category_moves = [];

    /**
     * Menu-only visibility. Does not enable or disable the category itself.
     *
     * @var list<array{category_id: int, show: bool}>
     */
    #[ORM\Column(type: 'json')]
    private array $menu_category_visibility = [];

    /**
     * Exact attribute value replacements applied during import.
     * category_id null means every attribute group.
     * attribute_id null means every attribute in that scope.
     *
     * @var list<array{from: string, to: string, category_id: int|null, attribute_id: int|null}>
     */
    #[ORM\Column(type: 'json')]
    private array $param_value_replacements = [];

    /**
     * Exact attribute name replacements applied during import.
     * Always global. The attribute slug stays based on the feed name.
     *
     * @var list<array{from: string, to: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $param_name_replacements = [];

    /**
     * Exact category name replacements applied during import.
     * The category URL is left as imported.
     *
     * @var list<array{from: string, to: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $category_name_replacements = [];

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

    /** @param list<string>|array<int|string, mixed>|null $disabledCategorySlugs */
    public function setDisabledCategorySlugs(?array $disabledCategorySlugs): static
    {
        $normalized = [];
        foreach ($disabledCategorySlugs ?? [] as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }
            $normalized[] = $slug;
        }
        $this->disabled_category_slugs = array_values(array_unique($normalized));

        return $this;
    }

    /** @return list<string> */
    public function getAppliedDisabledCategorySlugs(): array
    {
        return $this->applied_disabled_category_slugs;
    }

    /** @param list<string>|array<int|string, mixed>|null $slugs */
    public function setAppliedDisabledCategorySlugs(?array $slugs): static
    {
        $normalized = [];
        foreach ($slugs ?? [] as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }
            $normalized[] = $slug;
        }
        $this->applied_disabled_category_slugs = array_values(array_unique($normalized));

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

    /**
     * @return list<array{category_id: int, position: int}>
     */
    public function getMenuTopCategories(): array
    {
        return $this->menu_top_categories;
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $rows
     */
    public function setMenuTopCategories(array $rows): static
    {
        $normalized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId < 1 || isset($seen[$categoryId])) {
                continue;
            }
            $seen[$categoryId] = true;
            $position = (int) ($row['position'] ?? 0);
            if ($position < 0) {
                $position = 0;
            }
            $normalized[] = [
                'category_id' => $categoryId,
                'position' => $position,
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            return $a['position'] <=> $b['position'] ?: $a['category_id'] <=> $b['category_id'];
        });
        $this->menu_top_categories = $normalized;

        return $this;
    }

    /**
     * @return list<array{category_id: int, parent_id: int}>
     */
    public function getMenuCategoryMoves(): array
    {
        return $this->menu_category_moves;
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $rows
     */
    public function setMenuCategoryMoves(array $rows): static
    {
        $normalized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($categoryId < 1 || $parentId < 1 || $categoryId === $parentId || isset($seen[$categoryId])) {
                continue;
            }
            $seen[$categoryId] = true;
            $normalized[] = [
                'category_id' => $categoryId,
                'parent_id' => $parentId,
            ];
        }
        $this->menu_category_moves = $normalized;

        return $this;
    }

    /**
     * @return list<array{category_id: int, show: bool}>
     */
    public function getMenuCategoryVisibility(): array
    {
        return $this->menu_category_visibility;
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $rows
     */
    public function setMenuCategoryVisibility(array $rows): static
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId < 1) {
                continue;
            }
            $show = filter_var($row['show'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $normalized[$categoryId] = [
                'category_id' => $categoryId,
                'show' => $show,
            ];
        }
        $this->menu_category_visibility = array_values($normalized);

        return $this;
    }

    /**
     * @return list<array{from: string, to: string, category_id: int|null, attribute_id: int|null}>
     */
    public function getParamValueReplacements(): array
    {
        return $this->param_value_replacements;
    }

    /**
     * Admin form model: attribute multi-select + replacement pairs.
     * Stored flat in {@see $param_value_replacements}; import logic is unchanged.
     *
     * @return list<array{
     *     attribute_ids: list<int>,
     *     replacements: list<array{from: string, to: string}>,
     *     category_id: int|null
     * }>
     */
    public function getParamValueReplacementGroups(): array
    {
        /** @var array<string, array{attribute_ids: list<int>, replacements: list<array{from: string, to: string}>, category_id: int|null}> $byAttribute */
        $byAttribute = [];
        foreach ($this->param_value_replacements as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $from = trim((string) ($row['from'] ?? ''));
            $to = trim((string) ($row['to'] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            $categoryId = $categoryId > 0 ? $categoryId : null;
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            $attributeId = $attributeId > 0 ? $attributeId : null;
            // Legacy "whole category" rows stay keyed by category; attribute multi-select is empty.
            $key = $attributeId !== null ? 'a:'.$attributeId : 'c:'.($categoryId ?? 0);
            if (!isset($byAttribute[$key])) {
                $byAttribute[$key] = [
                    'attribute_ids' => $attributeId !== null ? [$attributeId] : [],
                    'replacements' => [],
                    'category_id' => $attributeId === null ? $categoryId : null,
                ];
            }
            $byAttribute[$key]['replacements'][] = [
                'from' => $from,
                'to' => $to,
            ];
        }

        // Merge attribute-specific blocks that share the same replacement list into one multi-select.
        // Never merge "all attributes" / legacy category-wide rows with attribute-scoped ones.
        $merged = [];
        foreach ($byAttribute as $block) {
            $isGlobal = $block['attribute_ids'] === [];
            $signature = ($isGlobal ? 'g:' : 'a:')
                .$this->paramValueReplacementSignature($block['replacements'], $block['category_id']);
            if (!isset($merged[$signature])) {
                $merged[$signature] = $block;
                continue;
            }
            if ($isGlobal) {
                continue;
            }
            $merged[$signature]['attribute_ids'] = array_values(array_unique(array_merge(
                $merged[$signature]['attribute_ids'],
                $block['attribute_ids'],
            )));
        }

        return array_values($merged);
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $groups
     */
    public function setParamValueReplacementGroups(array $groups): static
    {
        $flat = [];
        foreach ($groups as $group) {
            if (!\is_array($group)) {
                continue;
            }
            $categoryId = (int) ($group['category_id'] ?? 0);
            $categoryId = $categoryId > 0 ? $categoryId : null;
            $attributeIds = [];
            foreach ($group['attribute_ids'] ?? [] as $rawId) {
                $id = (int) $rawId;
                if ($id > 0) {
                    $attributeIds[$id] = $id;
                }
            }
            $attributeIds = array_values($attributeIds);
            $replacements = $group['replacements'] ?? [];
            if (!\is_array($replacements)) {
                continue;
            }

            $targets = $attributeIds !== [] ? $attributeIds : [null];
            foreach ($targets as $attributeId) {
                foreach ($replacements as $pair) {
                    if (!\is_array($pair)) {
                        continue;
                    }
                    $flat[] = [
                        'from' => $pair['from'] ?? '',
                        'to' => $pair['to'] ?? '',
                        // Category-wide legacy rules keep category_id only when no attributes are chosen.
                        'category_id' => $attributeId === null ? $categoryId : null,
                        'attribute_id' => $attributeId,
                    ];
                }
            }
        }

        return $this->setParamValueReplacements($flat);
    }

    /**
     * @param list<array{from: string, to: string}> $replacements
     */
    private function paramValueReplacementSignature(array $replacements, ?int $categoryId): string
    {
        $normalized = [];
        foreach ($replacements as $pair) {
            $normalized[] = trim($pair['from'])."\0".trim($pair['to']);
        }
        sort($normalized);

        return ($categoryId ?? 0)."\n".implode("\n", $normalized);
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $rows
     */
    public function setParamValueReplacements(array $rows): static
    {
        $normalized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $from = trim((string) ($row['from'] ?? ''));
            $to = trim((string) ($row['to'] ?? ''));
            $categoryId = (int) ($row['category_id'] ?? 0);
            $categoryId = $categoryId > 0 ? $categoryId : null;
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            $attributeId = $attributeId > 0 ? $attributeId : null;
            $key = $from."\0".($categoryId ?? 0)."\0".($attributeId ?? 0);
            if ($from === '' || $to === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = [
                'from' => $from,
                'to' => $to,
                'category_id' => $categoryId,
                'attribute_id' => $attributeId,
            ];
        }
        $this->param_value_replacements = $normalized;

        return $this;
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    public function getParamNameReplacements(): array
    {
        return $this->param_name_replacements;
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $rows
     */
    public function setParamNameReplacements(array $rows): static
    {
        $normalized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $from = trim((string) ($row['from'] ?? ''));
            $to = trim((string) ($row['to'] ?? ''));
            if ($from === '' || $to === '' || isset($seen[$from])) {
                continue;
            }
            $seen[$from] = true;
            $normalized[] = [
                'from' => $from,
                'to' => $to,
            ];
        }
        $this->param_name_replacements = $normalized;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getParamNameReplacementMap(): array
    {
        $map = [];
        foreach ($this->param_name_replacements as $row) {
            $map[$row['from']] = $row['to'];
        }

        return $map;
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    public function getCategoryNameReplacements(): array
    {
        return $this->category_name_replacements;
    }

    /**
     * @param list<array<string, mixed>>|array<int, mixed> $rows
     */
    public function setCategoryNameReplacements(array $rows): static
    {
        $normalized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $from = trim((string) ($row['from'] ?? ''));
            $to = trim((string) ($row['to'] ?? ''));
            if ($from === '' || $to === '' || isset($seen[$from])) {
                continue;
            }
            $seen[$from] = true;
            $normalized[] = [
                'from' => $from,
                'to' => $to,
            ];
        }
        $this->category_name_replacements = $normalized;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryNameReplacementMap(): array
    {
        $map = [];
        foreach ($this->category_name_replacements as $row) {
            $map[$row['from']] = $row['to'];
        }

        return $map;
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
