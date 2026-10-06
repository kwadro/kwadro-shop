<?php

namespace App\Service\ProductImport;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImport;
use App\Entity\ProductImportMode;
use App\Entity\ProductImportRun;
use App\Entity\ProductImportRunStatus;
use App\Entity\ProductOffer;
use App\Entity\Supplier;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * SiViTek price-sheet import/sync.
 * Existing products: update price, availability and description only.
 * New products: full create. Delete Add/Update mode disables missing products.
 */
final class SivitekProductImportService
{
    private const AVAILABILITY_VALUES = ['в наявності', 'очікується'];

    /** @var array<string, string> */
    private const SHEET_CATEGORY_SLUGS = [
        'UniBracket' => 'unibracket',
        'iTECHmount' => 'itechmount',
        'Brateck' => 'brateck',
        'для СВЧ' => 'dlya-svch',
        'настольные для мониторов и тв' => 'nastolnye-dlya-monitorov-i-tv',
        'Стійки, столи, тумби' => 'stiyky-stoly-tumby',
        'для проекторов' => 'dlya-proektorov',
        'Кабель TM Ultra' => 'kabel-tm-ultra',
        'Кабель TM Prolink' => 'kabel-tm-prolink',
        'Кабель TM Logan inc' => 'kabel-tm-logan-inc',
        'Подовжувачі ТМ Logan inc' => 'podovzhuvachi-tm-logan-inc',
        'LED лампи' => 'led-lampy',
        'Побутові товари' => 'pobutovi-tovary',
        'Тримачі для планшетів' => 'trymachi-dlya-planshetiv',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly SupplierRepository $supplierRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function run(ProductImport $import, bool $dryRun = false): ProductImportRun
    {
        $run = (new ProductImportRun())
            ->setProductImport($import)
            ->setStatus(ProductImportRunStatus::Running)
            ->setStartedAt(new \DateTimeImmutable());

        if (!$dryRun) {
            $this->em->persist($run);
            $this->em->flush();
        }

        try {
            $source = $this->resolveFilePath($import->getFilePath());
            if (!is_file($source)) {
                throw new \RuntimeException(sprintf('File not found: %s', $source));
            }

            $xlsx = $this->ensureXlsx($source);
            $parent = $this->categoryRepository->findOneBy(['slug' => 'kronshteyny']);
            if ($parent === null) {
                throw new \RuntimeException('Category "kronshteyny" not found.');
            }

            $supplier = $this->resolveSupplier();
            $workbook = $this->readWorkbook($xlsx);
            $disabledSkuMap = array_fill_keys(array_map('mb_strtolower', $import->getDisabledSkus()), true);
            $seenSkus = [];
            $created = 0;
            $updated = 0;
            $disabled = 0;

            foreach ($workbook['sheets'] as $sheetName => $rows) {
                if ($sheetName === 'Price' || !isset(self::SHEET_CATEGORY_SLUGS[$sheetName])) {
                    continue;
                }

                $category = $this->categoryRepository->findOneBy(['slug' => self::SHEET_CATEGORY_SLUGS[$sheetName]]);
                if ($category === null) {
                    continue;
                }

                foreach ($this->parseSheetRows($rows) as $row) {
                    $sku = $this->buildSku($row['model'], $row['brand']);
                    $seenSkus[mb_strtolower($sku)] = true;
                    $forceDisabled = isset($disabledSkuMap[mb_strtolower($sku)]);

                    $existing = $this->productRepository->findOneBy(['sku' => $sku]);
                    if ($existing === null) {
                        $product = $this->createProduct($row, $sku, $category, $parent, $supplier, $forceDisabled);
                        if (!$dryRun) {
                            $this->em->persist($product);
                        }
                        ++$created;
                        continue;
                    }

                    $this->syncExistingProduct($existing, $row, $sku, $supplier, $forceDisabled);
                    if ($forceDisabled) {
                        ++$disabled;
                    }
                    ++$updated;
                }
            }

            $deleted = 0;
            if ($import->getMode() === ProductImportMode::DeleteAddUpdate) {
                $deleted = $this->disableMissingProducts($supplier, $seenSkus, $dryRun);
            }

            // Force-disable configured SKUs even if absent from the current file.
            foreach ($import->getDisabledSkus() as $sku) {
                $product = $this->productRepository->findOneBy(['sku' => $sku]);
                if ($product === null || !$product->isEnabled()) {
                    continue;
                }
                $product->setEnabled(false);
                ++$disabled;
            }

            $summary = sprintf(
                'Created: %d, updated: %d, disabled: %d, deleted/deactivated: %d (mode: %s)',
                $created,
                $updated,
                $disabled,
                $deleted,
                $import->getMode()->label(),
            );

            $run
                ->setCreatedCount($created)
                ->setUpdatedCount($updated)
                ->setDisabledCount($disabled)
                ->setDeletedCount($deleted)
                ->setResult($summary)
                ->setStatus(ProductImportRunStatus::Success)
                ->setFinishedAt(new \DateTimeImmutable());

            $import
                ->setLastRunAt($run->getFinishedAt())
                ->setLastResult($summary);

            if (!$dryRun) {
                $this->em->flush();
            }
        } catch (\Throwable $e) {
            $run
                ->setStatus(ProductImportRunStatus::Failed)
                ->setErrorMessage($e->getMessage())
                ->setResult('Failed: '.$e->getMessage())
                ->setFinishedAt(new \DateTimeImmutable());

            $import
                ->setLastRunAt($run->getFinishedAt())
                ->setLastResult($run->getResult());

            if (!$dryRun) {
                $this->em->flush();
            }
        }

        return $run;
    }

    private function resolveFilePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return $this->projectDir.'/data-price/прайс опт SiViTek.xls';
        }
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectDir.'/'.ltrim($path, '/');
    }

    /**
     * @param array{
     *   model: string, brand: string, color: string, type: string, description: string,
     *   price: ?float, availability: string, in_stock: bool, stock_qty: int
     * } $row
     */
    private function createProduct(
        array $row,
        string $sku,
        Category $category,
        Category $parent,
        Supplier $supplier,
        bool $forceDisabled,
    ): Product {
        $product = (new Product())
            ->setModel($row['model'])
            ->setBrand($row['brand'] !== '' ? $row['brand'] : null)
            ->setColor($row['color'] !== '' ? $row['color'] : null)
            ->setType($row['type'] !== '' ? $row['type'] : null)
            ->setSku($sku)
            ->rebuildNameFromModelBrand()
            ->setDescription($this->toHtmlDescription($row['description']))
            ->setShortDescription($this->toShortDescription($row['description']))
            ->setInStock($row['in_stock'])
            ->setStockQty($row['in_stock'] ? max(1, $row['stock_qty']) : 0)
            ->setBadge($row['in_stock'] ? null : 'Очікується')
            ->setOgType('product')
            ->setEnabled(!$forceDisabled)
            ->addCategory($category)
            ->addCategory($parent);

        $product->ensureSlug();
        $this->ensureUniqueProductSlug($product);

        if ($row['price'] !== null && $row['price'] > 0) {
            $this->upsertOffer($product, $supplier, $sku, $row['price'], $product->getStockQty());
        }

        return $product;
    }

    /**
     * Sync only description, price and availability for existing products.
     *
     * @param array{
     *   model: string, brand: string, color: string, type: string, description: string,
     *   price: ?float, availability: string, in_stock: bool, stock_qty: int
     * } $row
     */
    private function syncExistingProduct(
        Product $product,
        array $row,
        string $sku,
        Supplier $supplier,
        bool $forceDisabled,
    ): void {
        $product
            ->setDescription($this->toHtmlDescription($row['description']))
            ->setShortDescription($this->toShortDescription($row['description']))
            ->setInStock($row['in_stock'])
            ->setStockQty($row['in_stock'] ? max(1, $row['stock_qty']) : 0)
            ->setBadge($row['in_stock'] ? null : 'Очікується');

        if ($forceDisabled) {
            $product->setEnabled(false);
        }

        if ($row['price'] !== null && $row['price'] > 0) {
            $this->upsertOffer($product, $supplier, $sku, $row['price'], $product->getStockQty());
        }
    }

    /**
     * @param array<string, true> $seenSkus lowercase sku map
     */
    private function disableMissingProducts(Supplier $supplier, array $seenSkus, bool $dryRun): int
    {
        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.offers', 'o')
            ->innerJoin('o.supplier', 's')
            ->andWhere('s = :supplier')
            ->setParameter('supplier', $supplier)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }
            $skuKey = mb_strtolower($product->getSku());
            if (isset($seenSkus[$skuKey])) {
                continue;
            }
            if (!$product->isEnabled()) {
                continue;
            }
            $product->setEnabled(false);
            ++$count;
        }

        if (!$dryRun && $count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    private function ensureXlsx(string $source): string
    {
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            return $source;
        }

        $targetDir = sys_get_temp_dir();
        $cmd = sprintf(
            'libreoffice --headless --convert-to xlsx --outdir %s %s 2>&1',
            escapeshellarg($targetDir),
            escapeshellarg($source),
        );
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new \RuntimeException("Failed to convert XLS to XLSX:\n".implode("\n", $output));
        }

        $xlsx = $targetDir.'/'.pathinfo($source, PATHINFO_FILENAME).'.xlsx';
        if (!is_file($xlsx)) {
            throw new \RuntimeException('Converted XLSX not found: '.$xlsx);
        }

        return $xlsx;
    }

    /**
     * @return array{shared: list<string>, sheets: array<string, array<int, array<string, string>>>}
     */
    private function readWorkbook(string $xlsx): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($xlsx) !== true) {
            throw new \RuntimeException('Cannot open XLSX: '.$xlsx);
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $ss = $this->loadXml($sharedXml);
            foreach ($ss->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text .= (string) $si->t;
                }
                foreach ($si->r ?? [] as $run) {
                    if (isset($run->t)) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }

        $wb = $this->loadXml((string) $zip->getFromName('xl/workbook.xml'));
        $rels = $this->loadXml((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $ridToTarget = [];
        foreach ($rels->Relationship as $rel) {
            $ridToTarget[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $sheets = [];
        foreach ($wb->sheets->sheet as $sheet) {
            $name = (string) $sheet['name'];
            $rid = (string) ($sheet['id'] ?? '');
            if ($rid === '' || !isset($ridToTarget[$rid])) {
                continue;
            }
            $target = ltrim($ridToTarget[$rid], '/');
            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/'.$target;
            }
            $xml = $zip->getFromName($target);
            if ($xml === false) {
                continue;
            }
            $sheets[$name] = $this->parseSheetXml($xml, $shared);
        }

        $zip->close();

        return ['shared' => $shared, 'sheets' => $sheets];
    }

    private function loadXml(string $xml): \SimpleXMLElement
    {
        $stripped = preg_replace('/xmlns(:\w+)?="[^"]*"/i', '', $xml) ?? $xml;
        $stripped = preg_replace('/\b[a-zA-Z_][\w]*:/', '', $stripped) ?? $stripped;
        $element = simplexml_load_string($stripped);
        if ($element === false) {
            throw new \RuntimeException('Failed to parse XLSX XML part.');
        }

        return $element;
    }

    /**
     * @param list<string> $shared
     * @return array<int, array<string, string>>
     */
    private function parseSheetXml(string $xml, array $shared): array
    {
        $sheet = $this->loadXml($xml);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                if ($ref === '' || !preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                    continue;
                }
                $col = $m[1];
                $rowNum = (int) $m[2];
                if (!isset($cell->v)) {
                    continue;
                }
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $rows[$rowNum][$col] = $value;
            }
        }

        ksort($rows);

        return $rows;
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return list<array{
     *   model: string, brand: string, color: string, type: string, description: string,
     *   price: ?float, availability: string, in_stock: bool, stock_qty: int
     * }>
     */
    private function parseSheetRows(array $rows): array
    {
        $headerRow = null;
        $roles = [];

        foreach ($rows as $rowNum => $cols) {
            $normalized = [];
            foreach ($cols as $col => $value) {
                $normalized[$col] = mb_strtolower(trim(preg_replace('/\s+/u', '', $value) ?? $value));
            }
            $values = array_values($normalized);
            $isHeader = \in_array('модель', $values, true)
                || \in_array('бренд', $values, true)
                || (\in_array('назва', $values, true) && \in_array('опис', $values, true));
            if (!$isHeader) {
                continue;
            }

            $headerRow = $rowNum;
            foreach ($cols as $col => $header) {
                $h = mb_strtolower(trim(preg_replace('/\s+/u', '', $header) ?? $header));
                if ($h === 'бренд' && !isset($roles['brand'])) {
                    $roles['brand'] = $col;
                } elseif (($h === 'модель' || $h === 'назва') && !isset($roles['model'])) {
                    $roles['model'] = $col;
                } elseif ((str_contains($h, 'колір') || str_contains($h, 'цвет')) && !isset($roles['color'])) {
                    $roles['color'] = $col;
                } elseif (str_starts_with($h, 'тип') && !isset($roles['type'])) {
                    $roles['type'] = $col;
                } elseif ((str_contains($h, 'характерист') || $h === 'опис') && !isset($roles['desc'])) {
                    $roles['desc'] = $col;
                } elseif (str_contains($h, 'наявність') && !isset($roles['avail'])) {
                    $roles['avail'] = $col;
                } elseif ((str_starts_with($h, 'ціна') || str_starts_with($h, 'цена')) && !isset($roles['price'])) {
                    $roles['price'] = $col;
                }
            }
            if (isset($cols['D']) && str_contains(mb_strtolower($cols['D']), 'характерист')) {
                $roles['desc'] = 'D';
            }
            if (isset($cols['G']) && str_contains(mb_strtolower($cols['G']), 'наявність')) {
                $roles['avail'] = 'G';
            }
            break;
        }

        if ($headerRow === null || !isset($roles['model'], $roles['avail'])) {
            return [];
        }

        $products = [];
        $currentType = null;
        $sectionBrand = null;

        foreach ($rows as $rowNum => $cols) {
            if ($rowNum <= $headerRow) {
                continue;
            }

            $get = static function (string $role) use ($roles, $cols): string {
                $col = $roles[$role] ?? null;

                return $col !== null ? trim((string) ($cols[$col] ?? '')) : '';
            };

            $brand = $get('brand');
            $model = $get('model');
            $desc = $get('desc');
            $color = $get('color');
            $typeCol = $get('type');
            $avail = $get('avail');
            $priceRaw = $get('price');

            if ($model === '' && $brand === '') {
                $sectionText = $typeCol !== '' ? $typeCol : $desc;
                if ($sectionText !== '' && !isset($roles['type'])) {
                    if (mb_strlen($sectionText) <= 80 || !str_contains($sectionText, "\n")) {
                        $currentType = $sectionText;
                    }
                }
                if ($sectionText !== '' && str_contains(mb_strtolower($sectionText), 'fundesk')) {
                    $sectionBrand = 'FunDesk';
                }
                continue;
            }

            if ($model === '') {
                continue;
            }

            $availability = mb_strtolower(trim($avail));
            if (!\in_array($availability, self::AVAILABILITY_VALUES, true)) {
                continue;
            }

            if ($brand === '' && $sectionBrand !== null) {
                $brand = $sectionBrand;
            }

            $type = $typeCol !== '' ? $typeCol : ($currentType ?? '');
            $inStock = $availability === 'в наявності';

            $products[] = [
                'model' => $model,
                'brand' => $brand,
                'color' => $color,
                'type' => trim($type),
                'description' => $desc,
                'price' => $this->parsePrice($priceRaw),
                'availability' => $avail,
                'in_stock' => $inStock,
                'stock_qty' => $inStock ? 1 : 0,
            ];
        }

        return $products;
    }

    private function parsePrice(string $raw): ?float
    {
        $raw = trim(str_replace(["\xc2\xa0", ' '], '', $raw));
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(',', '.', $raw);
        if (!is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    private function buildSku(string $model, string $brand): string
    {
        $sku = trim($model);
        if ($brand !== '') {
            $candidate = $sku.'-'.$brand;
            if (mb_strlen($candidate) <= 64) {
                $sku = $candidate;
            }
        }

        return mb_substr($sku, 0, 64);
    }

    private function toHtmlDescription(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<p>'.nl2br($escaped, false).'</p>';
    }

    private function toShortDescription(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > 220 ? mb_substr($text, 0, 217).'...' : $text;
    }

    private function resolveSupplier(): Supplier
    {
        $supplier = $this->supplierRepository->findOneBy(['slug' => 'sivitek'])
            ?? $this->supplierRepository->findOneBy(['name' => 'SiViTek']);

        if ($supplier !== null) {
            return $supplier;
        }

        $supplier = (new Supplier())
            ->setName('SiViTek')
            ->setSlug('sivitek')
            ->setDescription('Import from SiViTek price sheet');
        $this->em->persist($supplier);
        $this->em->flush();

        return $supplier;
    }

    private function upsertOffer(Product $product, Supplier $supplier, string $sku, float $price, int $qty): void
    {
        $offer = null;
        foreach ($product->getOffers() as $existing) {
            if ($existing->getSupplier()?->getId() === $supplier->getId()) {
                $offer = $existing;
                break;
            }
        }

        if ($offer === null) {
            $offer = new ProductOffer();
            $offer->setSupplier($supplier);
            $product->addOffer($offer);
        }

        $offer
            ->setSku($sku)
            ->setPrice($price)
            ->setQty($qty);
    }

    private function ensureUniqueProductSlug(Product $product): void
    {
        $product->ensureSlug();
        $base = $product->getSlug();
        $slug = $base;
        $i = 2;
        while ($this->productRepository->slugExists($slug, $product->getId())) {
            $slug = $base.'-'.$i;
            ++$i;
        }
        $product->setSlug($slug);
    }
}
