<?php

namespace App\Command;

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
    name: 'app:product:update-rrc-from-price',
    description: 'Update Kronshteyny product prices from Price sheet column N (РРЦ) by model in column A',
)]
final class ProductUpdateRrcFromPriceCommand extends Command
{
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report only, do not write');
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

        $category = $this->categoryRepository->findOneBy(['slug' => 'kronshteyny']);
        if ($category === null) {
            $io->error('Category kronshteyny not found.');

            return Command::FAILURE;
        }

        try {
            $xlsx = $this->ensureXlsx($source);
            $pricesByModel = $this->readPriceSheetRrc($xlsx);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->text(sprintf('Price sheet models with RRC: %d', \count($pricesByModel)));

        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->leftJoin('p.offers', 'o')
            ->addSelect('o')
            ->leftJoin('o.supplier', 's')
            ->addSelect('s')
            ->andWhere('c = :category')
            ->setParameter('category', $category)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $supplier = $this->resolveSupplier();
        $updated = 0;
        $created = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $model = trim((string) $product->getModel());
            if ($model === '') {
                ++$skipped;
                continue;
            }

            $price = $pricesByModel[$model] ?? $pricesByModel[mb_strtolower($model)] ?? null;
            if ($price === null) {
                ++$missing;
                $io->text(sprintf('  ? no Price row for model %s (%s)', $model, $product->getSku()));
                continue;
            }

            $offer = $product->getOffers()->first() ?: null;
            if ($offer instanceof ProductOffer) {
                $old = $offer->getPrice();
                if (abs($old - $price) < 0.009) {
                    ++$skipped;
                    continue;
                }
                if (!$dryRun) {
                    $offer->setPrice($price);
                }
                ++$updated;
                $io->text(sprintf('  ~ %s: %.2f → %.2f', $product->getName(), $old, $price));
            } else {
                if (!$dryRun) {
                    $offer = (new ProductOffer())
                        ->setSupplier($supplier)
                        ->setSku($product->getSku())
                        ->setPrice($price)
                        ->setQty($product->isInStock() ? max(1, $product->getStockQty()) : 0);
                    $product->addOffer($offer);
                    $this->em->persist($offer);
                }
                ++$created;
                $io->text(sprintf('  + %s: %.2f (new offer)', $product->getName(), $price));
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%sUpdated: %d, created offers: %d, unchanged/skipped: %d, missing in Price: %d',
            $dryRun ? '[dry-run] ' : '',
            $updated,
            $created,
            $skipped,
            $missing,
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, float> model => RRC price
     */
    private function readPriceSheetRrc(string $xlsx): array
    {
        $workbook = $this->readWorkbook($xlsx);
        $rows = $workbook['sheets']['Price'] ?? null;
        if ($rows === null) {
            throw new \RuntimeException('Sheet "Price" not found.');
        }

        $prices = [];
        foreach ($rows as $rowNum => $cols) {
            if ($rowNum < 3) {
                continue;
            }
            $model = trim((string) ($cols['A'] ?? ''));
            $rrcRaw = trim((string) ($cols['N'] ?? ''));
            if ($model === '' || mb_strtolower($model) === 'модель') {
                continue;
            }
            $price = $this->parsePrice($rrcRaw);
            if ($price === null || $price <= 0) {
                continue;
            }
            $prices[$model] = $price;
            $prices[mb_strtolower($model)] = $price;
        }

        return $prices;
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

    private function resolveSupplier(): Supplier
    {
        $supplier = $this->supplierRepository->findOneBy(['slug' => 'sivitek'])
            ?? $this->supplierRepository->findOneBy(['name' => 'SiViTek']);

        if ($supplier !== null) {
            return $supplier;
        }

        $supplier = (new Supplier())
            ->setName('SiViTek')
            ->setSlug('sivitek');
        $this->em->persist($supplier);
        $this->em->flush();

        return $supplier;
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
     * @return array{sheets: array<string, array<int, array<string, string>>>}
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

        return ['sheets' => $sheets];
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
                if (!isset($cell->v)) {
                    continue;
                }
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $rows[(int) $m[2]][$m[1]] = $value;
            }
        }

        ksort($rows);

        return $rows;
    }
}
