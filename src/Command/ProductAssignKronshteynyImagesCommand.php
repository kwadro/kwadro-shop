<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\Product\ProductImagePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:product:assign-kronshteyny-images',
    description: 'Assign gallery images for Kronshteyny products from {model}.png / {model}.jpg pattern',
)]
final class ProductAssignKronshteynyImagesCommand extends Command
{
    /** @var list<string> */
    private const SUFFIXES = ['', '_1', '_2', '_3', '_4'];

    /** @var list<string> Prefer PNG, then JPG */
    private const EXTENSIONS = ['png', 'jpg'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
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

        $copied = $this->copyFromSource($sourceDir, $dryRun, $io);

        $category = $this->categoryRepository->findOneBy(['slug' => 'kronshteyny']);
        if ($category === null) {
            $io->error('Category kronshteyny not found.');

            return Command::FAILURE;
        }

        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c = :category')
            ->setParameter('category', $category)
            ->orderBy('p.model', 'ASC')
            ->getQuery()
            ->getResult();

        $updated = 0;
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

            $gallery = $this->buildGalleryForModel($model, $product->getName(), $dryRun);
            if ($gallery === []) {
                ++$missing;
                $io->text(sprintf('  ? no images for %s', $model));
                continue;
            }

            if (!$dryRun) {
                $product->setGallery($gallery);
            }
            ++$updated;
            $io->text(sprintf('  ✓ %s (%d images)', $model, \count($gallery)));
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%sUpdated: %d, missing images: %d, skipped: %d, copied files: %d',
            $dryRun ? '[dry-run] ' : '',
            $updated,
            $missing,
            $skipped,
            $copied,
        ));

        return Command::SUCCESS;
    }

    private function copyFromSource(string $sourceDir, bool $dryRun, SymfonyStyle $io): int
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

            $target = ProductImagePath::absolutePath($this->projectDir, $filename);
            if (is_file($target)) {
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
            $io->text(sprintf('  copy %s → %s', $file, ProductImagePath::relativePath($filename)));
        }

        return $copied;
    }

    /**
     * @return list<array{thumb: string, full: string, alt: string}>
     */
    private function buildGalleryForModel(string $model, string $productName, bool $dryRun): array
    {
        $gallery = [];
        foreach (self::SUFFIXES as $suffix) {
            $filename = $this->resolveImageFilename($model.$suffix, $dryRun);
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
    private function resolveImageFilename(string $basename, bool $dryRun): ?string
    {
        foreach (self::EXTENSIONS as $ext) {
            $filename = $basename.'.'.$ext;
            $target = ProductImagePath::absolutePath($this->projectDir, $filename);
            $flat = $this->projectDir.'/public'.ProductImagePath::WEB_BASE.'/'.$filename;

            if (!is_file($target) && is_file($flat) && !$dryRun) {
                ProductImagePath::ensureStored($this->projectDir, $filename);
            }

            if (is_file($target) || is_file($flat)) {
                return $filename;
            }
        }

        return null;
    }
}
