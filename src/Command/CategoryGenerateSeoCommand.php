<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\Product\ProductImagePath;
use App\Service\Category\CategoryOgImagePath;
use App\Service\Product\ProductOgImageGenerator;
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
    name: 'app:category:generate-seo',
    description: 'Generate category SEO JSON + unique 1200×630 OG images into og-categories/',
)]
final class CategoryGenerateSeoCommand extends Command
{
    private const BRAND = 'Квадро';
    private const META_TITLE_MAX = 70;
    private const META_DESCRIPTION_MAX = 160;
    private const DEFAULT_DIR = 'og-categories';
    private const THEME_DIR = 'public/uploads/images/og-category-themes';

    /** Shared placeholders that should be replaced with unique per-category images. */
    private const SHARED_FALLBACKS = [
        'og-category-lugi.jpg',
        'og-category-digital-tb.jpg',
    ];

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
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
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Export directory', self::DEFAULT_DIR)
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Generate only this category slug (probe / single rebuild)')
            ->addOption('force-images', null, InputOption::VALUE_NONE, 'Rebuild OG images for every category (ignores existing exports)')
            ->addOption('all-images', null, InputOption::VALUE_NONE, 'Generate OG for every storefront category (not only missing/shared)')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Also write SEO/OG fields into the local database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $this->resolveDir((string) $input->getOption('dir'));
        $imagesDir = $dir;
        $jsonPath = $dir.'/category-seo.json';
        $forceImages = (bool) $input->getOption('force-images');
        $allImages = (bool) $input->getOption('all-images');
        $apply = (bool) $input->getOption('apply');
        $onlySlug = trim((string) ($input->getOption('slug') ?? ''));

        $default = $this->categoryRepository->findDefaultItem();
        if ($default === null) {
            $io->error('Default root category not found.');

            return Command::FAILURE;
        }

        $categories = [];
        foreach ($this->categoryRepository->findAll() as $category) {
            if (!$category->isEnabled()) {
                continue;
            }
            if ($onlySlug !== '' && $category->getSlug() !== $onlySlug) {
                continue;
            }
            $topLevel = $this->resolveTopLevel($category, $default);
            if ($topLevel === null) {
                continue;
            }
            $categories[] = [$category, $topLevel];
        }

        if ($onlySlug !== '' && $categories === []) {
            $io->error(sprintf('Category slug "%s" not found (or not under Default).', $onlySlug));

            return Command::FAILURE;
        }

        $io->progressStart(\count($categories));

        $rows = [];
        $imagesGenerated = 0;
        $imagesSkipped = 0;
        $errors = [];

        foreach ($categories as [$category, $topLevel]) {
            $io->progressAdvance();
            \assert($category instanceof Category);
            \assert($topLevel instanceof Category);

            $meta = $this->buildMeta($category, $topLevel);
            // --force-images / --all-images rebuild every category; otherwise only missing/shared OGs.
            $needsImage = $onlySlug !== '' || $allImages || $forceImages || $this->needsUniqueImage($category);
            $ogFilename = ProductOgImageGenerator::categoryOgFilename($category->getSlug());
            $targetImage = $imagesDir.'/'.$ogFilename;
            $sourceLabel = null;

            if ($needsImage) {
                try {
                    if ($forceImages || $onlySlug !== '' || $allImages || !is_file($targetImage)) {
                        $themeBackground = $this->resolveThemeBackgroundPath($category, $topLevel);
                        $this->ogImageGenerator->writeCategoryFeatureJpeg(
                            $category->getName(),
                            null,
                            $targetImage,
                            90,
                            $themeBackground,
                        );
                        $sourceLabel = sprintf(
                            'theme=%s',
                            $themeBackground !== null ? basename($themeBackground) : 'neutral-fallback',
                        );
                        ++$imagesGenerated;
                    } else {
                        ++$imagesSkipped;
                        $sourceLabel = 'existing-export';
                    }
                } catch (\Throwable $e) {
                    $errors[] = sprintf('#%d %s: %s', $category->getId(), $category->getSlug(), $e->getMessage());
                    $ogFilename = trim((string) $category->getOgImage());
                    $sourceLabel = null;
                }
            } else {
                $existing = trim((string) $category->getOgImage());
                if ($existing !== '') {
                    $ogFilename = CategoryOgImagePath::filename($existing);
                    $publicPath = CategoryOgImagePath::absolutePath($this->projectDir, $ogFilename);
                    $legacyPath = $this->projectDir.'/public/uploads/images/'.$ogFilename;
                    foreach ([$publicPath, $legacyPath] as $candidate) {
                        if ($candidate !== '' && is_file($candidate) && !is_file($targetImage)) {
                            copy($candidate, $targetImage);
                            break;
                        }
                    }
                    $sourceLabel = 'kept-existing';
                } else {
                    $ogFilename = '';
                }
            }

            $row = [
                'id' => $category->getId(),
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'meta_title' => $meta['meta_title'],
                'meta_description' => $meta['meta_description'],
                'og_title' => $meta['og_title'],
                'og_description' => $meta['og_description'],
                'og_type' => 'website',
                'og_image' => $ogFilename,
                'source_image' => $sourceLabel,
                'image_generated' => $needsImage,
            ];
            $rows[] = $row;

            if ($apply) {
                $this->applyRow($category, $row, $dir);
            }
        }

        $io->progressFinish();

        $payload = [
            'generated_at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            'brand' => self::BRAND,
            'og_image_size' => [
                'width' => ProductOgImageGenerator::WIDTH,
                'height' => ProductOgImageGenerator::HEIGHT,
            ],
            'images_dir' => '.',
            'count' => \count($rows),
            'categories' => $rows,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($jsonPath, $json."\n") === false) {
            $io->error('Cannot write JSON: '.$jsonPath);

            return Command::FAILURE;
        }

        if ($apply) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Exported %d categories → %s (new images: %d, reused export files: %d)',
            \count($rows),
            $jsonPath,
            $imagesGenerated,
            $imagesSkipped,
        ));

        if ($errors !== []) {
            $io->warning('First errors:');
            $io->listing(\array_slice($errors, 0, 15));
        }

        $io->note([
            'Apply on live: php bin/console app:category:apply-seo --dir='.$dir,
            'Transfer folder: rsync -avz --progress og-categories/ USER@HOST:/path/to/project/og-categories/',
        ]);

        return $errors === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array{meta_title: string, meta_description: string, og_title: string, og_description: string} */
    private function buildMeta(Category $category, Category $topLevel): array
    {
        $name = trim($category->getName());

        $metaTitle = !$this->isBlank($category->getMetaTitle())
            ? trim((string) $category->getMetaTitle())
            : $this->buildMetaTitle($name);

        $metaDescription = !$this->isBlank($category->getMetaDescription())
            ? $this->plainText((string) $category->getMetaDescription(), self::META_DESCRIPTION_MAX)
            : $this->truncate(sprintf(
                '%s у каталозі «%s»: актуальні ціни та наявність. Доставка Новою Поштою по Україні, оплата онлайн або при отриманні.',
                $name,
                trim($topLevel->getName()),
            ), self::META_DESCRIPTION_MAX);

        $ogTitle = !$this->isBlank($category->getOgTitle())
            ? trim((string) $category->getOgTitle())
            : $this->truncate(sprintf('%s | %s', $name, self::BRAND), 95);

        $ogDescription = !$this->isBlank($category->getOgDescription())
            ? $this->plainText((string) $category->getOgDescription(), self::META_DESCRIPTION_MAX)
            : $this->truncate(sprintf(
                '%s — ціни, наявність і доставка по Україні в магазині %s.',
                $name,
                self::BRAND,
            ), self::META_DESCRIPTION_MAX);

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
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

    private function needsUniqueImage(Category $category): bool
    {
        $current = CategoryOgImagePath::filename($category->getOgImage());
        if ($current === '') {
            return true;
        }

        if (\in_array($current, self::SHARED_FALLBACKS, true)) {
            return true;
        }

        $expected = ProductOgImageGenerator::categoryOgFilename($category->getSlug());

        return $current !== $expected;
    }

    /**
     * Pick a lifestyle theme for this category:
     * 1) exact slug file(s) in og-category-themes/ (slug.jpg, slug-2.jpg…)
     * 2) otherwise spread across the full theme pool by slug hash
     *    (so homeware children do not all share the same 2–3 homeware photos)
     */
    private function resolveThemeBackgroundPath(Category $category, Category $topLevel): ?string
    {
        $themeDir = $this->projectDir.'/'.self::THEME_DIR;
        $slug = trim($category->getSlug());
        if ($slug !== '' && $slug !== Category::DEFAULT_SLUG) {
            $variants = $this->listThemeVariants($themeDir, $slug);
            if ($variants !== []) {
                return $variants[abs(crc32($slug)) % \count($variants)];
            }
        }

        $pool = $this->listAllThemeFiles($themeDir);
        if ($pool === []) {
            return null;
        }

        // Include top-level slug in the seed so sibling trees still diversify stably.
        $seed = 'theme:'.$slug.':'.trim($topLevel->getSlug());

        return $pool[abs(crc32($seed)) % \count($pool)];
    }

    /** @return list<string> absolute paths of all theme images in the themes directory */
    private function listAllThemeFiles(string $themeDir): array
    {
        if (!is_dir($themeDir)) {
            return [];
        }

        $paths = [];
        foreach (['jpg', 'jpeg', 'webp', 'png'] as $ext) {
            foreach (glob($themeDir.'/*.'.$ext) ?: [] as $path) {
                if (is_file($path)) {
                    $paths[] = $path;
                }
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        return $paths;
    }

    /** @return list<string> absolute paths, stable order */
    private function listThemeVariants(string $themeDir, string $slug): array
    {
        $paths = [];
        foreach (['.jpg', '.jpeg', '.webp', '.png'] as $ext) {
            $base = $themeDir.'/'.$slug.$ext;
            if (is_file($base)) {
                $paths[] = $base;
            }
            foreach (glob($themeDir.'/'.$slug.'-*'.$ext) ?: [] as $variant) {
                if (is_file($variant)) {
                    $paths[] = $variant;
                }
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        return $paths;
    }

    private function resolveCategorySourceImage(Category $category): ?string
    {
        $queue = [$category];
        $seen = [];
        while ($queue !== []) {
            $node = array_shift($queue);
            if (!$node instanceof Category) {
                continue;
            }
            $id = $node->getId();
            if ($id === null || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            foreach ($this->findProductsWithGallery($node, 40) as $product) {
                $path = $this->resolveProductVisualPath($product);
                if ($path !== null) {
                    return $path;
                }
            }

            foreach ($node->getChildren() as $child) {
                if ($child->isEnabled()) {
                    $queue[] = $child;
                }
            }
        }

        return null;
    }

    /** @return list<Product> */
    private function findProductsWithGallery(Category $category, int $limit): array
    {
        /** @var list<Product> $products */
        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->leftJoin('p.offers', 'o')->addSelect('o')
            ->leftJoin('o.supplier', 's')->addSelect('s')
            ->andWhere('c = :category')
            ->andWhere('p.enabled = true')
            ->setParameter('category', $category)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $products,
            static fn (Product $product): bool => $product->getGallery() !== []
                || (trim((string) $product->getOgImage()) !== ''),
        ));
    }

    private function resolveProductVisualPath(Product $product): ?string
    {
        return $this->resolveFirstGalleryPath($product) ?? $this->resolveProductOgPath($product);
    }

    private function resolveProductOgPath(Product $product): ?string
    {
        $candidates = [];
        $og = trim((string) $product->getOgImage());
        if ($og !== '') {
            $abs = ProductOgImagePath::absolutePath($this->projectDir, $og);
            if ($abs !== '') {
                $candidates[] = $abs;
            }
            $filename = ProductOgImagePath::filename($og);
            if ($filename !== '') {
                $candidates[] = $this->projectDir.'/product-og/'.$filename;
                $candidates[] = $this->projectDir.'/var/export/product-seo/images/'.$filename;
                $candidates[] = $this->projectDir.'/public/uploads/images/'.$filename;
            }
        }

        $skuOg = ProductOgImageGenerator::ogImageFilename($product->getSku(), $product->getId());
        $candidates[] = $this->projectDir.'/product-og/'.$skuOg;
        $candidates[] = $this->projectDir.'/var/export/product-seo/images/'.$skuOg;
        $candidates[] = ProductOgImagePath::absolutePath($this->projectDir, $skuOg);

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
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

    /** @param array<string, mixed> $row */
    private function applyRow(Category $category, array $row, string $exportDir): void
    {
        $category->setMetaTitle((string) $row['meta_title']);
        $category->setMetaDescription((string) $row['meta_description']);
        $category->setOgTitle((string) $row['og_title']);
        $category->setOgDescription((string) $row['og_description']);
        $category->setOgType((string) $row['og_type']);
        if (($row['og_image'] ?? '') !== '') {
            $filename = CategoryOgImagePath::filename((string) $row['og_image']);
            $category->setOgImage($filename);
            $src = $exportDir.'/'.basename((string) $row['og_image']);
            $dst = CategoryOgImagePath::absolutePath($this->projectDir, $filename);
            if ($filename !== '' && is_file($src) && $dst !== '') {
                $dstDir = \dirname($dst);
                if (!is_dir($dstDir)) {
                    mkdir($dstDir, 0775, true);
                }
                if (!is_file($dst) || md5_file($src) !== md5_file($dst)) {
                    copy($src, $dst);
                }
            }
        }
    }

    private function resolveTopLevel(Category $category, Category $default): ?Category
    {
        $node = $category;
        while ($node->getParent() !== null) {
            if ($node->getParent() === $default) {
                return $node;
            }
            $node = $node->getParent();
        }

        return null;
    }

    private function plainText(string $html, int $max): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
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
