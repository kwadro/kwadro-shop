<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductOffer;
use App\Entity\Supplier;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:product:import-sivitek',
    description: 'Import SiViTek price sheet products into shop categories',
)]
final class ProductImportSivitekCommand extends Command
{
    private const AVAILABILITY_VALUES = ['в наявності', 'очікується'];

    /** @var array<string, string> sheet name => category slug */
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
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'file',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to SiViTek .xls/.xlsx price file',
                $this->projectDir.'/data-price/прайс опт SiViTek.xls',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and report without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $source = (string) $input->getOption('file');

        if (!is_file($source)) {
            $io->error(sprintf('File not found: %s', $source));

            return Command::FAILURE;
        }

        try {
            $xlsx = $this->ensureXlsx($source);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $parent = $this->categoryRepository->findOneBy(['slug' => 'kronshteyny']);
        if ($parent === null) {
            $io->error('Category "Кронштейни" (kronshteyny) not found. Run app:category:seed-kronshteyny first.');

            return Command::FAILURE;
        }

        $supplier = $this->resolveSupplier();
        $workbook = $this->readWorkbook($xlsx);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($workbook['sheets'] as $sheetName => $rows) {
            if ($sheetName === 'Price' || !isset(self::SHEET_CATEGORY_SLUGS[$sheetName])) {
                continue;
            }

            $category = $this->categoryRepository->findOneBy(['slug' => self::SHEET_CATEGORY_SLUGS[$sheetName]]);
            if ($category === null) {
                $io->warning(sprintf('Skip sheet "%s": category slug "%s" missing.', $sheetName, self::SHEET_CATEGORY_SLUGS[$sheetName]));
                continue;
            }

            $parsed = $this->parseSheetRows($rows);
            $io->section(sprintf('%s → %s (%d rows)', $sheetName, $category->getSlug(), \count($parsed)));

            foreach ($parsed as $row) {
                $sku = $this->buildSku($row['model'], $row['brand']);
                $existing = $this->productRepository->findOneBy(['sku' => $sku]);
                $isNew = $existing === null;
                $product = $existing ?? new Product();

                $product
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
                    ->addCategory($category)
                    ->addCategory($parent);

                if ($product->getSlug() === '') {
                    $product->ensureSlug();
                }
                $this->ensureUniqueProductSlug($product);

                if ($row['price'] !== null && $row['price'] > 0) {
                    $this->upsertOffer($product, $supplier, $sku, $row['price'], $product->getStockQty());
                }

                if (!$dryRun) {
                    $this->em->persist($product);
                }

                if ($isNew) {
                    ++$created;
                    $io->text(sprintf('  + %s [%s]', $product->getName(), $row['availability']));
                } else {
                    ++$updated;
                    $io->text(sprintf('  ~ %s [%s]', $product->getName(), $row['availability']));
                }
            }

            if ($parsed === []) {
                ++$skipped;
                $io->note('No importable products on this sheet.');
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%sCreated: %d, updated: %d, empty sheets: %d',
            $dryRun ? '[dry-run] ' : '',
            $created,
            $updated,
            $skipped,
        ));

        return Command::SUCCESS;
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
     *   model: string,
     *   brand: string,
     *   color: string,
     *   type: string,
     *   description: string,
     *   price: ?float,
     *   availability: string,
     *   in_stock: bool,
     *   stock_qty: int
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
            // Prefer classic mount layout: description in D when header says so.
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

            // Section header rows (type group): filled description/type text, no model.
            if ($model === '' && $brand === '') {
                $sectionText = $typeCol !== '' ? $typeCol : $desc;
                if ($sectionText !== '' && !isset($roles['type'])) {
                    // Short section titles for mounts / cables.
                    if (mb_strlen($sectionText) <= 80 || !str_contains($sectionText, "\n")) {
                        $currentType = $sectionText;
                    }
                }
                // LED-like brand section titles in description column.
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
        $html = nl2br($escaped, false);

        return '<p>'.$html.'</p>';
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
            ->setDescription('Імпорт з прайсу SiViTek');
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
