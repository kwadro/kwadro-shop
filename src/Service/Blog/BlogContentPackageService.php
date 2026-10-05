<?php

namespace App\Service\Blog;

use App\Entity\BlogArticle;
use App\Entity\BlogCategory;
use App\Entity\Locale;
use App\Entity\Site;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogCategoryRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class BlogContentPackageService
{
    private const PACKAGE_VERSION = 1;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly BlogArticleRepository $articleRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{path: string, categories: int, articles: int, assets: int}
     */
    public function export(?string $siteCode = null, ?string $localeCode = null, ?string $targetPath = null): array
    {
        $categories = $this->loadCategories($siteCode, $localeCode);
        $articles = $this->loadArticles($siteCode, $localeCode);

        $categoryRows = [];
        foreach ($categories as $category) {
            $categoryRows[] = $this->serializeCategory($category);
        }

        $articleRows = [];
        $assetPaths = [];
        foreach ($articles as $article) {
            $articleRows[] = $this->serializeArticle($article);
            foreach ($this->extractUploadPaths($article->getContent()) as $path) {
                $assetPaths[$path] = true;
            }
            $ogImage = $article->getOgImage();
            if ($ogImage !== null && $ogImage !== '') {
                $assetPaths['/uploads/images/'.ltrim($ogImage, '/')] = true;
            }
        }

        foreach ($categories as $category) {
            $ogImage = $category->getOgImage();
            if ($ogImage !== null && $ogImage !== '') {
                $assetPaths['/uploads/og-images/'.ltrim($ogImage, '/')] = true;
            }
        }

        // Always include the whole blog uploads tree when present (logos etc.).
        foreach ($this->listBlogUploadFiles() as $path) {
            $assetPaths[$path] = true;
        }

        foreach ($this->listOgImageFiles() as $path) {
            $assetPaths[$path] = true;
        }

        $manifest = [
            'version' => self::PACKAGE_VERSION,
            'exported_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'categories' => $categoryRows,
            'articles' => $articleRows,
            'assets' => array_keys($assetPaths),
        ];

        $path = $targetPath ?? $this->defaultExportPath();
        $dir = \dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create export directory: %s', $dir));
        }

        $this->writeZip($path, $manifest, array_keys($assetPaths));

        return [
            'path' => $path,
            'categories' => \count($categoryRows),
            'articles' => \count($articleRows),
            'assets' => \count($assetPaths),
        ];
    }

    /**
     * @return array{categories_created: int, categories_updated: int, articles_created: int, articles_updated: int, assets: int}
     */
    public function import(string $packagePath, ?int $forceSiteId = null, bool $dryRun = false): array
    {
        if (!is_file($packagePath)) {
            throw new \InvalidArgumentException(sprintf('Package not found: %s', $packagePath));
        }

        $zip = new \ZipArchive();
        if ($zip->open($packagePath) !== true) {
            throw new \RuntimeException(sprintf('Cannot open package: %s', $packagePath));
        }

        try {
            $manifestJson = $zip->getFromName('manifest.json');
            if ($manifestJson === false) {
                throw new \RuntimeException('Package is missing manifest.json');
            }

            /** @var array<string, mixed>|null $manifest */
            $manifest = json_decode($manifestJson, true);
            if (!\is_array($manifest) || (int) ($manifest['version'] ?? 0) !== self::PACKAGE_VERSION) {
                throw new \RuntimeException('Unsupported or invalid blog package manifest');
            }

            /** @var list<array<string, mixed>> $categoryRows */
            $categoryRows = \is_array($manifest['categories'] ?? null) ? $manifest['categories'] : [];
            /** @var list<array<string, mixed>> $articleRows */
            $articleRows = \is_array($manifest['articles'] ?? null) ? $manifest['articles'] : [];

            usort($categoryRows, static function (array $a, array $b): int {
                $pa = $a['parent_slug'] ?? null;
                $pb = $b['parent_slug'] ?? null;
                if ($pa === null && $pb !== null) {
                    return -1;
                }
                if ($pa !== null && $pb === null) {
                    return 1;
                }

                return ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0));
            });

            $stats = [
                'categories_created' => 0,
                'categories_updated' => 0,
                'articles_created' => 0,
                'articles_updated' => 0,
                'assets' => 0,
            ];

            if ($dryRun) {
                $stats['categories_created'] = \count($categoryRows);
                $stats['articles_created'] = \count($articleRows);
                $stats['assets'] = $this->countZipAssets($zip);

                return $stats;
            }

            $categoryMap = [];
            foreach ($categoryRows as $row) {
                [$site, $locale] = $this->resolveSiteAndLocale($row, $forceSiteId);
                $slug = (string) ($row['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }

                $category = $this->categoryRepository->findOneBy([
                    'site' => $site,
                    'locale' => $locale,
                    'slug' => $slug,
                ]);
                $isNew = $category === null;
                if ($isNew) {
                    $category = new BlogCategory();
                    $category->setSite($site);
                    $category->setLocale($locale);
                    $category->setSlug($slug);
                }

                $category
                    ->setName((string) ($row['name'] ?? $slug))
                    ->setMetaTitle(isset($row['meta_title']) ? (string) $row['meta_title'] : null)
                    ->setMetaDescription(isset($row['meta_description']) ? (string) $row['meta_description'] : null)
                    ->setOgTitle(isset($row['og_title']) ? (string) $row['og_title'] : null)
                    ->setOgDescription(isset($row['og_description']) ? (string) $row['og_description'] : null)
                    ->setOgType(isset($row['og_type']) ? (string) $row['og_type'] : null)
                    ->setOgImage(isset($row['og_image']) ? (string) $row['og_image'] : null)
                    ->setEnabled((bool) ($row['enabled'] ?? true))
                    ->setPosition((int) ($row['position'] ?? 0));

                $parentSlug = $row['parent_slug'] ?? null;
                if (\is_string($parentSlug) && $parentSlug !== '') {
                    $parentKey = $this->categoryKey($site, $locale, $parentSlug);
                    $parent = $categoryMap[$parentKey]
                        ?? $this->categoryRepository->findOneBy([
                            'site' => $site,
                            'locale' => $locale,
                            'slug' => $parentSlug,
                        ]);
                    $category->setParent($parent);
                } else {
                    $category->setParent(null);
                }

                $this->em->persist($category);
                $this->em->flush();

                $categoryMap[$this->categoryKey($site, $locale, $slug)] = $category;
                if ($isNew) {
                    ++$stats['categories_created'];
                } else {
                    ++$stats['categories_updated'];
                }
            }

            foreach ($articleRows as $row) {
                [$site, $locale] = $this->resolveSiteAndLocale($row, $forceSiteId);
                $slug = (string) ($row['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }

                $article = $this->articleRepository->findOneBy([
                    'site' => $site,
                    'locale' => $locale,
                    'slug' => $slug,
                ]);
                $isNew = $article === null;
                if ($isNew) {
                    $article = new BlogArticle();
                    $article->setSite($site);
                    $article->setLocale($locale);
                    $article->setSlug($slug);
                }

                $article
                    ->setTitle((string) ($row['title'] ?? $slug))
                    ->setMetaTitle(isset($row['meta_title']) ? (string) $row['meta_title'] : null)
                    ->setMetaDescription(isset($row['meta_description']) ? (string) $row['meta_description'] : null)
                    ->setOgTitle(isset($row['og_title']) ? (string) $row['og_title'] : null)
                    ->setOgDescription(isset($row['og_description']) ? (string) $row['og_description'] : null)
                    ->setOgType(isset($row['og_type']) ? (string) $row['og_type'] : null)
                    ->setOgImage(isset($row['og_image']) ? (string) $row['og_image'] : null)
                    ->setTags(isset($row['tags']) ? (string) $row['tags'] : null)
                    ->setContent((string) ($row['content'] ?? ''))
                    ->setFacebookDraft(isset($row['facebook_draft']) ? (string) $row['facebook_draft'] : null)
                    ->setEnabled((bool) ($row['enabled'] ?? true));

                $publishedAt = $row['published_at'] ?? null;
                if (\is_string($publishedAt) && $publishedAt !== '') {
                    $article->setPublishedAt(new \DateTimeImmutable($publishedAt));
                }

                foreach ($article->getCategories()->toArray() as $existingCategory) {
                    $article->removeCategory($existingCategory);
                }

                $categorySlugs = $row['category_slugs'] ?? [];
                if (\is_array($categorySlugs)) {
                    foreach ($categorySlugs as $categorySlug) {
                        if (!\is_string($categorySlug) || $categorySlug === '') {
                            continue;
                        }
                        $key = $this->categoryKey($site, $locale, $categorySlug);
                        $category = $categoryMap[$key]
                            ?? $this->categoryRepository->findOneBy([
                                'site' => $site,
                                'locale' => $locale,
                                'slug' => $categorySlug,
                            ]);
                        if ($category !== null) {
                            $article->addCategory($category);
                        }
                    }
                }

                $this->em->persist($article);
                $this->em->flush();

                if ($isNew) {
                    ++$stats['articles_created'];
                } else {
                    ++$stats['articles_updated'];
                }
            }

            $stats['assets'] = $this->extractAssetsFromZip($zip);

            return $stats;
        } finally {
            $zip->close();
        }
    }

    /** @return list<BlogCategory> */
    private function loadCategories(?string $siteCode, ?string $localeCode): array
    {
        $qb = $this->categoryRepository->createQueryBuilder('c')
            ->leftJoin('c.site', 's')->addSelect('s')
            ->leftJoin('c.locale', 'l')->addSelect('l')
            ->leftJoin('c.parent', 'p')->addSelect('p')
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if ($siteCode !== null && $siteCode !== '') {
            $qb->andWhere('s.code = :siteCode')->setParameter('siteCode', $siteCode);
        }
        if ($localeCode !== null && $localeCode !== '') {
            $qb->andWhere('l.code = :localeCode')->setParameter('localeCode', $localeCode);
        }

        /** @var list<BlogCategory> $items */
        $items = $qb->getQuery()->getResult();

        return $items;
    }

    /** @return list<BlogArticle> */
    private function loadArticles(?string $siteCode, ?string $localeCode): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->leftJoin('a.site', 's')->addSelect('s')
            ->leftJoin('a.locale', 'l')->addSelect('l')
            ->leftJoin('a.categories', 'c')->addSelect('c')
            ->orderBy('a.publishedAt', 'ASC')
            ->addOrderBy('a.id', 'ASC');

        if ($siteCode !== null && $siteCode !== '') {
            $qb->andWhere('s.code = :siteCode')->setParameter('siteCode', $siteCode);
        }
        if ($localeCode !== null && $localeCode !== '') {
            $qb->andWhere('l.code = :localeCode')->setParameter('localeCode', $localeCode);
        }

        /** @var list<BlogArticle> $items */
        $items = $qb->getQuery()->getResult();

        return $items;
    }

    /** @return array<string, mixed> */
    private function serializeCategory(BlogCategory $category): array
    {
        return [
            'site_code' => $category->getSite()?->getCode(),
            'locale_code' => $category->getLocale()?->getCode(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'meta_title' => $category->getMetaTitle(),
            'meta_description' => $category->getMetaDescription(),
            'og_title' => $category->getOgTitle(),
            'og_description' => $category->getOgDescription(),
            'og_type' => $category->getOgType(),
            'og_image' => $category->getOgImage(),
            'enabled' => $category->isEnabled(),
            'position' => $category->getPosition(),
            'parent_slug' => $category->getParent()?->getSlug(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeArticle(BlogArticle $article): array
    {
        $categorySlugs = [];
        foreach ($article->getCategories() as $category) {
            $categorySlugs[] = $category->getSlug();
        }

        return [
            'site_code' => $article->getSite()?->getCode(),
            'locale_code' => $article->getLocale()?->getCode(),
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'meta_title' => $article->getMetaTitle(),
            'meta_description' => $article->getMetaDescription(),
            'og_title' => $article->getOgTitle(),
            'og_description' => $article->getOgDescription(),
            'og_type' => $article->getOgType(),
            'og_image' => $article->getOgImage(),
            'tags' => $article->getTags(),
            'content' => $article->getContent(),
            'facebook_draft' => $article->getFacebookDraft(),
            'enabled' => $article->isEnabled(),
            'published_at' => $article->getPublishedAt()?->format(DATE_ATOM),
            'category_slugs' => $categorySlugs,
        ];
    }

    /** @return list<string> */
    private function extractUploadPaths(string $content): array
    {
        preg_match_all('#(?:src|href)=["\'](/uploads/[^"\']+)["\']#i', $content, $matches);
        $paths = [];
        foreach ($matches[1] ?? [] as $path) {
            $normalized = '/'.ltrim(str_replace('\\', '/', $path), '/');
            if (str_starts_with($normalized, '/uploads/')) {
                $paths[] = $normalized;
            }
        }

        return array_values(array_unique($paths));
    }

    /** @return list<string> */
    private function listBlogUploadFiles(): array
    {
        return $this->listUploadFilesUnder('uploads/blog');
    }

    /** @return list<string> */
    private function listOgImageFiles(): array
    {
        return $this->listUploadFilesUnder('uploads/og-images');
    }

    /** @return list<string> */
    private function listUploadFilesUnder(string $relativeDir): array
    {
        $root = $this->projectDir.'/public/'.$relativeDir;
        if (!is_dir($root)) {
            return [];
        }

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = substr($absolute, \strlen($this->projectDir.'/public'));
            $relative = '/'.ltrim(str_replace('\\', '/', $relative), '/');
            $paths[] = $relative;
        }

        return $paths;
    }

    /** @param list<string> $assetPaths */
    private function writeZip(string $path, array $manifest, array $assetPaths): void
    {
        if (is_file($path)) {
            unlink($path);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException(sprintf('Cannot create package: %s', $path));
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        foreach ($assetPaths as $webPath) {
            $absolute = $this->projectDir.'/public'.$webPath;
            if (!is_file($absolute)) {
                continue;
            }
            $zip->addFile($absolute, 'assets'.$webPath);
        }

        $zip->close();
    }

    private function extractAssetsFromZip(\ZipArchive $zip): int
    {
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_starts_with($name, 'assets/uploads/')) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                continue;
            }

            $webPath = substr($name, \strlen('assets'));
            $target = $this->projectDir.'/public'.$webPath;
            $dir = \dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Cannot create asset directory: %s', $dir));
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }
            if (file_put_contents($target, $contents) === false) {
                throw new \RuntimeException(sprintf('Cannot write asset: %s', $target));
            }
            ++$count;
        }

        return $count;
    }

    private function countZipAssets(\ZipArchive $zip): int
    {
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && str_starts_with($name, 'assets/uploads/') && !str_ends_with($name, '/')) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{0: Site, 1: Locale}
     */
    private function resolveSiteAndLocale(array $row, ?int $forceSiteId): array
    {
        $site = null;
        if ($forceSiteId !== null) {
            $site = $this->siteRepository->find($forceSiteId);
        }
        if ($site === null) {
            $siteCode = (string) ($row['site_code'] ?? '');
            if ($siteCode !== '') {
                $site = $this->siteRepository->findOneBy(['code' => $siteCode]);
            }
        }
        if ($site === null) {
            $site = $this->siteRepository->find(1);
        }
        if ($site === null) {
            throw new \RuntimeException('Target site not found for blog import');
        }

        $localeCode = (string) ($row['locale_code'] ?? '');
        $locale = $localeCode !== ''
            ? $this->localeRepository->findOneBy(['code' => $localeCode])
            : null;
        if ($locale === null) {
            throw new \RuntimeException(sprintf('Locale "%s" not found for blog import', $localeCode !== '' ? $localeCode : '(empty)'));
        }

        return [$site, $locale];
    }

    private function categoryKey(Site $site, Locale $locale, string $slug): string
    {
        return ($site->getId() ?? 0).'|'.($locale->getId() ?? 0).'|'.$slug;
    }

    private function defaultExportPath(): string
    {
        return $this->projectDir.'/var/export/blog-package.zip';
    }
}
