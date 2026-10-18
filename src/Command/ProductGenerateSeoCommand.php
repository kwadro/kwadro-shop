<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Product\ProductImagePath;
use App\Service\Product\ProductOgImageGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:product:generate-seo',
    description: 'Generate product SEO/OG metadata JSON and {sku}_og.jpg images (enabled products with gallery only)',
)]
final class ProductGenerateSeoCommand extends Command
{
    private const BRAND = 'Квадро';
    private const META_TITLE_MAX = 70;
    private const META_DESCRIPTION_MAX = 160;
    private const DEFAULT_DIR = 'product-og';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductOgImageGenerator $ogImageGenerator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Export directory (images + product-seo.json)', self::DEFAULT_DIR)
            ->addOption('images-subdir', null, InputOption::VALUE_REQUIRED, 'Subfolder for images; empty = write images into --dir', '')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max products to process (0 = all)', '0')
            ->addOption('force-images', null, InputOption::VALUE_NONE, 'Rebuild OG images even if file already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $this->resolveDir((string) $input->getOption('dir'));
        $subdir = trim((string) $input->getOption('images-subdir'), '/');
        $imagesDir = $subdir === '' ? $dir : $dir.'/'.$subdir;
        $jsonPath = $dir.'/product-seo.json';
        $limit = max(0, (int) $input->getOption('limit'));
        $forceImages = (bool) $input->getOption('force-images');

        if (!is_dir($imagesDir) && !mkdir($imagesDir, 0775, true) && !is_dir($imagesDir)) {
            $io->error('Cannot create images directory: '.$imagesDir);

            return Command::FAILURE;
        }

        $ids = $this->productRepository->createQueryBuilder('p')
            ->select('p.id')
            ->andWhere('p.enabled = true')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        if ($limit > 0) {
            $ids = \array_slice($ids, 0, $limit);
        }

        $io->progressStart(\count($ids));

        $products = [];
        $usedNames = [];
        $generated = 0;
        $skippedInactiveOrEmpty = 0;
        $skippedNoImageFile = 0;
        $errors = [];

        foreach (array_chunk($ids, 50) as $chunk) {
            /** @var list<Product> $batch */
            $batch = $this->productRepository->createQueryBuilder('p')
                ->leftJoin('p.offers', 'o')->addSelect('o')
                ->leftJoin('o.supplier', 's')->addSelect('s')
                ->andWhere('p.id IN (:ids)')
                ->setParameter('ids', $chunk)
                ->orderBy('p.id', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($batch as $product) {
                $io->progressAdvance();

                if (!$product->isEnabled()) {
                    ++$skippedInactiveOrEmpty;
                    continue;
                }

                $ogFilename = ProductOgImageGenerator::ogImageFilename($product->getSku(), $product->getId());
                if (isset($usedNames[$ogFilename])) {
                    $ogFilename = ProductOgImageGenerator::ogImageFilename(
                        $product->getSku().'-'.$product->getId(),
                        $product->getId(),
                    );
                }
                $usedNames[$ogFilename] = true;

                $targetImage = $imagesDir.'/'.$ogFilename;
                $source = $this->resolveFirstGalleryPath($product);
                $sourceLabel = null;

                try {
                    if ($source !== null && ($forceImages || !is_file($targetImage))) {
                        $this->ogImageGenerator->writeContainJpeg($source, $targetImage);
                        $sourceLabel = basename($source);
                    } elseif (is_file($targetImage)) {
                        // Keep previously exported OG when local gallery file is missing.
                        $sourceLabel = $source !== null ? basename($source) : 'existing-export';
                    } elseif ($source !== null) {
                        $this->ogImageGenerator->writeContainJpeg($source, $targetImage);
                        $sourceLabel = basename($source);
                    } else {
                        if ($product->getGallery() === []) {
                            ++$skippedInactiveOrEmpty;
                        } else {
                            ++$skippedNoImageFile;
                        }
                        continue;
                    }
                } catch (\Throwable $e) {
                    $errors[] = sprintf('#%d %s: %s', $product->getId(), $product->getSku(), $e->getMessage());
                    continue;
                }

                if (!is_file($targetImage)) {
                    ++$skippedNoImageFile;
                    continue;
                }

                $meta = $this->buildMeta($product);
                $products[] = [
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'slug' => $product->getSlug(),
                    'name' => $product->getName(),
                    'meta_title' => $meta['meta_title'],
                    'meta_description' => $meta['meta_description'],
                    'og_title' => $meta['og_title'],
                    'og_description' => $meta['og_description'],
                    'og_type' => 'product',
                    'og_image' => $ogFilename,
                    'source_image' => $sourceLabel,
                ];
                ++$generated;
            }

            $this->entityManager->clear();
        }

        $io->progressFinish();

        $payload = [
            'generated_at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            'brand' => self::BRAND,
            'og_image_size' => [
                'width' => ProductOgImageGenerator::WIDTH,
                'height' => ProductOgImageGenerator::HEIGHT,
            ],
            'images_dir' => $subdir === '' ? '.' : $subdir,
            'count' => \count($products),
            'products' => $products,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($jsonPath, $json."\n") === false) {
            $io->error('Cannot write JSON: '.$jsonPath);

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Generated %d products → %s (%d images in %s)',
            $generated,
            $jsonPath,
            $generated,
            $imagesDir,
        ));
        $io->listing([
            'Skipped (disabled / empty gallery): '.$skippedInactiveOrEmpty,
            'Skipped (gallery set but file missing): '.$skippedNoImageFile,
            'Errors: '.\count($errors),
        ]);

        if ($errors !== []) {
            $io->warning('First errors:');
            $io->listing(\array_slice($errors, 0, 15));
        }

        $applyImages = $subdir === '' ? ' --images-dir='.$dir : '';
        $io->note('Apply on live with: php bin/console app:product:apply-seo --dir='.$dir.$applyImages);

        return $errors === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array{meta_title: string, meta_description: string, og_title: string, og_description: string} */
    private function buildMeta(Product $product): array
    {
        $name = trim($product->getName());
        $metaTitle = $this->buildMetaTitle($name);
        $description = $this->plainText($product->getShortDescription(), self::META_DESCRIPTION_MAX);
        if ($description === '') {
            $description = $this->plainText($product->getDescription(), self::META_DESCRIPTION_MAX);
        }
        if ($description === '') {
            $description = sprintf(
                'Купити %s в інтернет-магазині %s. Актуальна ціна, наявність і доставка Новою Поштою по Україні.',
                $name,
                self::BRAND,
            );
            $description = $this->truncate($description, self::META_DESCRIPTION_MAX);
        }

        $ogTitle = sprintf('%s | %s', $name, self::BRAND);
        if (mb_strlen($ogTitle) > 95) {
            $ogTitle = $this->truncate($name, 90).' | '.self::BRAND;
        }

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $description,
            'og_title' => $ogTitle,
            'og_description' => $description,
        ];
    }

    private function buildMetaTitle(string $name): string
    {
        $long = sprintf('%s — купити в інтернет-магазині | %s', $name, self::BRAND);
        if (mb_strlen($long) <= self::META_TITLE_MAX) {
            return $long;
        }

        $mid = sprintf('%s — купити | %s', $name, self::BRAND);
        if (mb_strlen($mid) <= self::META_TITLE_MAX) {
            return $mid;
        }

        $suffix = ' | '.self::BRAND;
        $maxName = self::META_TITLE_MAX - mb_strlen($suffix);

        return $this->truncate($name, max(10, $maxName)).$suffix;
    }

    private function plainText(?string $html, int $max): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $this->truncate($text, $max);
    }

    private function truncate(string $text, int $max): string
    {
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1), " \t.,;:!-").'…';
    }

    private function resolveFirstGalleryPath(Product $product): ?string
    {
        $gallery = $product->getGallery();
        if ($gallery === []) {
            return null;
        }

        $first = $gallery[0];
        if (!\is_array($first)) {
            return null;
        }

        $filename = ProductImagePath::filename((string) ($first['full'] ?? $first['thumb'] ?? ''));
        if ($filename === '') {
            return null;
        }

        $supplier = $product->getImageSupplierSlug();
        $candidates = [
            ProductImagePath::absolutePath($this->projectDir, $filename, $supplier),
            ProductImagePath::absolutePath($this->projectDir, $filename, null),
            rtrim($this->projectDir, '/').'/public'.ProductImagePath::WEB_BASE.'/'.$filename,
            rtrim($this->projectDir, '/').'/data-product/image/'.$filename,
        ];

        $publicRoot = rtrim($this->projectDir, '/').'/public'.ProductImagePath::WEB_BASE;
        foreach (['mahazyn-kvadro', 'sivitek', 'homeware', 'lugi'] as $code) {
            $candidates[] = ProductImagePath::absolutePath($this->projectDir, $filename, $code);
            $candidates[] = $publicRoot.'/'.$code.'/'.$filename;
        }

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        // Last resort: look under each supplier shard dir.
        $shard = ProductImagePath::shardDir($filename).'/'.$filename;
        $supplierDirs = glob($publicRoot.'/*', GLOB_ONLYDIR) ?: [];
        foreach ($supplierDirs as $supplierDir) {
            $path = $supplierDir.'/'.$shard;
            if (is_file($path)) {
                return $path;
            }
            $flat = $supplierDir.'/'.$filename;
            if (is_file($flat)) {
                return $flat;
            }
        }

        return null;
    }

    private function resolveDir(string $dir): string
    {
        if (!str_starts_with($dir, '/')) {
            $dir = $this->projectDir.'/'.ltrim($dir, './');
        }

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create directory: '.$dir);
        }

        return rtrim($dir, '/');
    }
}
