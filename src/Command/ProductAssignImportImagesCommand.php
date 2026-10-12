<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImport;
use App\Repository\CategoryRepository;
use App\Repository\ProductImportRepository;
use App\Repository\ProductRepository;
use App\Service\Product\ProductImagePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:product:assign-import-images',
    description: 'Assign gallery images for products under a product-import default category ({model}.png / {model}_1.png …)',
    aliases: ['app:product:assign-kronshteyny-images'],
)]
final class ProductAssignImportImagesCommand extends Command
{
    /** @var list<string> */
    private const SUFFIXES = ['', '_1', '_2', '_3', '_4'];

    /** @var list<string> Prefer PNG, then JPG */
    private const EXTENSIONS = ['png', 'jpg'];

    /**
     * Fallback category slug when import has no default_category_external_id.
     *
     * @var array<string, string>
     */
    private const IMPORT_CODE_CATEGORY_SLUG = [
        ProductImport::CODE_SIVITEK => 'kronshteyny',
        ProductImport::CODE_LUGI => 'lugi',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly ProductImportRepository $productImportRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'import-code',
                InputArgument::REQUIRED,
                'Product import code (e.g. sivitek, lugi). Products are taken from that import default category.',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report only, do not write')
            ->addOption(
                'source-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional source dir to copy missing images from',
                $this->projectDir.'/data-product/image',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $sourceDir = rtrim((string) $input->getOption('source-dir'), '/');
        $importCode = trim(mb_strtolower((string) $input->getArgument('import-code')));

        if ($importCode === '') {
            $io->error('Import code is required (e.g. sivitek or lugi).');

            return Command::FAILURE;
        }

        $import = $this->productImportRepository->findOneByCode($importCode);
        if ($import === null) {
            $io->error(sprintf('Product import with code "%s" not found.', $importCode));

            return Command::FAILURE;
        }

        $category = $this->resolveDefaultCategory($import);
        if ($category === null) {
            $io->error(sprintf(
                'Default category for import "%s" not found (external_id=%s).',
                $importCode,
                $import->getDefaultCategoryExternalId() ?? 'null',
            ));

            return Command::FAILURE;
        }

        $supplierSlug = $this->resolveSupplierSlug($importCode);
        $io->title(sprintf(
            'Assign images · import=%s · category=%s (id=%d, external_id=%s) · supplier=%s',
            $importCode,
            $category->getSlug(),
            (int) $category->getId(),
            $category->getExternalId() ?? '—',
            $supplierSlug,
        ));

        $copied = $this->copyFromSource($sourceDir, $supplierSlug, $dryRun, $io);

        $categoryIds = $this->collectCategoryIds($category);
        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->orderBy('p.model', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $io->text(sprintf('Products in category tree: %d', \count($products)));

        $updated = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $imageKey = $this->resolveImageKey($product, $importCode);
            if ($imageKey === '') {
                ++$skipped;
                continue;
            }

            $productSupplier = $product->getImageSupplierSlug() ?: $supplierSlug;
            $gallery = $this->buildGalleryForModel($imageKey, $product->getName(), $productSupplier, $dryRun);
            if ($gallery === []) {
                ++$missing;
                $io->text(sprintf('  ? no images for %s', $imageKey));
                continue;
            }

            if (!$dryRun) {
                $product->setGallery($gallery);
                $product->relocateGalleryFiles($this->projectDir);
            }
            ++$updated;
            $io->text(sprintf(
                '  ✓ %s → %s (%d images)',
                $imageKey,
                ProductImagePath::relativePath($gallery[0]['full'], $productSupplier),
                \count($gallery),
            ));
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%sUpdated: %d, missing images: %d, skipped (no model/sku): %d, copied files: %d',
            $dryRun ? '[dry-run] ' : '',
            $updated,
            $missing,
            $skipped,
            $copied,
        ));

        return Command::SUCCESS;
    }

    private function resolveDefaultCategory(ProductImport $import): ?Category
    {
        $externalId = $import->getDefaultCategoryExternalId();
        if ($externalId !== null) {
            $category = $this->categoryRepository->findOneBy(['external_id' => $externalId]);
            if ($category instanceof Category) {
                return $category;
            }
        }

        $slug = self::IMPORT_CODE_CATEGORY_SLUG[$import->getCode()] ?? null;
        if ($slug === null) {
            return null;
        }

        return $this->categoryRepository->findOneBy(['slug' => $slug]);
    }

    private function resolveSupplierSlug(string $importCode): string
    {
        return ProductImagePath::sanitizeSupplierCode($importCode) ?: $importCode;
    }

    /**
     * Image basename key: product model, or SKU without "{importCode}-" prefix.
     */
    private function resolveImageKey(Product $product, string $importCode): string
    {
        $model = trim((string) $product->getModel());
        if ($model !== '') {
            return $model;
        }

        $sku = trim((string) $product->getSku());
        if ($sku === '') {
            return '';
        }

        $prefix = $importCode.'-';
        if (str_starts_with(mb_strtolower($sku), $prefix)) {
            return trim(substr($sku, \strlen($prefix)));
        }

        return $sku;
    }

    /**
     * @return list<int>
     */
    private function collectCategoryIds(Category $root): array
    {
        $ids = [];
        $walk = function (Category $category) use (&$walk, &$ids): void {
            $id = $category->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
            foreach ($category->getChildren() as $child) {
                $walk($child);
            }
        };
        $walk($root);

        return array_values(array_unique($ids));
    }

    private function copyFromSource(string $sourceDir, string $supplierSlug, bool $dryRun, SymfonyStyle $io): int
    {
        if (!is_dir($sourceDir)) {
            return 0;
        }

        $copied = 0;
        foreach (scandir($sourceDir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $src = $sourceDir.'/'.$file;
            if (!is_file($src)) {
                continue;
            }

            $filename = ProductImagePath::filename($file);
            if ($filename === '') {
                continue;
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!\in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }

            $target = ProductImagePath::absolutePath($this->projectDir, $filename, $supplierSlug);
            if ($target === '' || is_file($target)) {
                continue;
            }

            if (!$dryRun) {
                $targetDir = \dirname($target);
                if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                    $io->warning(sprintf('Cannot create dir for %s', $filename));
                    continue;
                }
                if (!copy($src, $target)) {
                    $io->warning(sprintf('Failed to copy %s', $file));
                    continue;
                }
            }
            ++$copied;
            $io->text(sprintf('  copy %s → %s', $file, ProductImagePath::relativePath($filename, $supplierSlug)));
        }

        return $copied;
    }

    /**
     * @return list<array{thumb: string, full: string, alt: string}>
     */
    private function buildGalleryForModel(string $model, string $productName, string $supplierSlug, bool $dryRun): array
    {
        $gallery = [];
        foreach (self::SUFFIXES as $suffix) {
            $filename = $this->resolveImageFilename($model.$suffix, $supplierSlug, $dryRun);
            if ($filename === null) {
                continue;
            }

            $gallery[] = [
                'thumb' => $filename,
                'full' => $filename,
                'alt' => $productName !== '' ? $productName : $model,
            ];
        }

        return $gallery;
    }

    /**
     * Prefer .png, then .jpg. Returns basename only when a file exists (or will after relocate).
     */
    private function resolveImageFilename(string $basename, string $supplierSlug, bool $dryRun): ?string
    {
        foreach (self::EXTENSIONS as $ext) {
            $filename = $basename.'.'.$ext;
            $supplierPath = ProductImagePath::absolutePath($this->projectDir, $filename, $supplierSlug);
            $legacyPath = ProductImagePath::absolutePath($this->projectDir, $filename, null);
            $flat = $this->projectDir.'/public'.ProductImagePath::WEB_BASE.'/'.$filename;

            if (!$dryRun && $supplierPath !== '' && !is_file($supplierPath)) {
                ProductImagePath::ensureStored($this->projectDir, $filename, $supplierSlug);
            }

            if (
                ($supplierPath !== '' && is_file($supplierPath))
                || ($legacyPath !== '' && is_file($legacyPath))
                || is_file($flat)
            ) {
                return $filename;
            }
        }

        return null;
    }
}
