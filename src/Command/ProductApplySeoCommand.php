<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Product\ProductOgImagePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:product:apply-seo',
    description: 'Apply product SEO/OG JSON + copy images into public/uploads/images/og-images/{a}/{b}/ and the database',
)]
final class ProductApplySeoCommand extends Command
{
    private const DEFAULT_DIR = 'product-og';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Export directory with product-seo.json', self::DEFAULT_DIR)
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to product-seo.json (overrides --dir/product-seo.json)')
            ->addOption('images-dir', null, InputOption::VALUE_REQUIRED, 'Directory with OG images (default: --dir, or <dir>/images if present)')
            ->addOption('only-empty', null, InputOption::VALUE_NONE, 'Only fill blank SEO fields / missing og_image')
            ->addOption('skip-images', null, InputOption::VALUE_NONE, 'Update DB only, do not copy image files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show counts without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $this->resolvePath((string) $input->getOption('dir'));
        $file = $input->getOption('file');
        $jsonPath = \is_string($file) && $file !== ''
            ? $this->resolvePath($file)
            : $dir.'/product-seo.json';

        $imagesDirOpt = $input->getOption('images-dir');
        if (\is_string($imagesDirOpt) && $imagesDirOpt !== '') {
            $imagesDir = $this->resolvePath($imagesDirOpt);
        } elseif (is_dir($dir.'/images')) {
            $imagesDir = $dir.'/images';
        } else {
            $imagesDir = $dir;
        }

        $onlyEmpty = (bool) $input->getOption('only-empty');
        $skipImages = (bool) $input->getOption('skip-images');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_file($jsonPath)) {
            $io->error('JSON not found: '.$jsonPath);

            return Command::FAILURE;
        }

        $raw = file_get_contents($jsonPath);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (!\is_array($data) || !isset($data['products']) || !\is_array($data['products'])) {
            $io->error('Invalid product-seo.json structure.');

            return Command::FAILURE;
        }

        $updated = 0;
        $skipped = 0;
        $missingProducts = 0;
        $missingImages = 0;
        $copiedImages = 0;
        $errors = [];

        $io->progressStart(\count($data['products']));

        $n = 0;
        foreach ($data['products'] as $row) {
            $io->progressAdvance();
            if (!\is_array($row)) {
                ++$skipped;
                continue;
            }

            $product = $this->findProduct($row);
            if (!$product instanceof Product) {
                ++$missingProducts;
                continue;
            }

            $ogImage = trim((string) ($row['og_image'] ?? ''));
            $ogRelative = '';
            if ($ogImage !== '') {
                $ogFilename = ProductOgImagePath::filename($ogImage);
                $ogRelative = ProductOgImagePath::relativePath($ogFilename);
                $row['og_image'] = $ogRelative;

                if (!$skipImages) {
                    $src = $imagesDir.'/'.$ogFilename;
                    if (!is_file($src)) {
                        // Allow re-apply when export images are gone but public file already exists.
                        $src = ProductOgImagePath::absolutePath($this->projectDir, $ogFilename);
                    }
                    $dst = ProductOgImagePath::absolutePath($this->projectDir, $ogFilename);
                    if (!is_file($src)) {
                        ++$missingImages;
                        $errors[] = sprintf('Image missing for #%d %s: %s', $product->getId(), $product->getSku(), $ogFilename);
                    } elseif (!$dryRun && ($onlyEmpty === false || !is_file($dst))) {
                        $dstDir = \dirname($dst);
                        if (!is_dir($dstDir) && !mkdir($dstDir, 0775, true) && !is_dir($dstDir)) {
                            $errors[] = sprintf('Cannot create directory: %s', $dstDir);
                        } elseif ($src !== $dst && !copy($src, $dst)) {
                            $errors[] = sprintf('Cannot copy %s → %s', $src, $dst);
                        } else {
                            ++$copiedImages;
                        }
                    } elseif ($dryRun) {
                        ++$copiedImages;
                    }
                }
            }

            $changed = $this->applyRow($product, $row, $onlyEmpty);
            if ($changed === []) {
                ++$skipped;
            } else {
                ++$updated;
            }

            if (++$n % 100 === 0 && !$dryRun) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $io->progressFinish();

        if ($dryRun) {
            $this->entityManager->clear();
            $io->note('[dry-run] Nothing saved.');
        } else {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Products updated: %d | skipped unchanged: %d | missing products: %d | images copied: %d | missing images: %d',
            $updated,
            $skipped,
            $missingProducts,
            $copiedImages,
            $missingImages,
        ));

        if ($errors !== []) {
            $io->warning('First issues:');
            $io->listing(\array_slice($errors, 0, 20));
        }

        return $missingProducts > 0 || $missingImages > 0 || $errors !== []
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function applyRow(Product $product, array $row, bool $onlyEmpty): array
    {
        $changed = [];

        $map = [
            'meta_title' => 'setMetaTitle',
            'meta_description' => 'setMetaDescription',
            'og_title' => 'setOgTitle',
            'og_description' => 'setOgDescription',
            'og_type' => 'setOgType',
            'og_image' => 'setOgImage',
        ];

        foreach ($map as $key => $setter) {
            if (!\array_key_exists($key, $row)) {
                continue;
            }
            $value = trim((string) $row[$key]);
            if ($value === '') {
                continue;
            }

            $getter = match ($key) {
                'meta_title' => $product->getMetaTitle(),
                'meta_description' => $product->getMetaDescription(),
                'og_title' => $product->getOgTitle(),
                'og_description' => $product->getOgDescription(),
                'og_type' => $product->getOgType(),
                'og_image' => $product->getOgImage(),
                default => null,
            };

            if ($onlyEmpty && !$this->isBlank($getter)) {
                continue;
            }

            if ((string) $getter === $value) {
                continue;
            }

            $product->{$setter}($value);
            $changed[] = $key;
        }

        return $changed;
    }

    /** @param array<string, mixed> $row */
    private function findProduct(array $row): ?Product
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id > 0) {
            $product = $this->productRepository->find($id);
            if ($product instanceof Product) {
                return $product;
            }
        }

        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            return null;
        }

        return $this->productRepository->findOneBy(['sku' => $sku]);
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function resolvePath(string $path): string
    {
        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir.'/'.ltrim($path, './');
        }

        return rtrim($path, '/');
    }
}
