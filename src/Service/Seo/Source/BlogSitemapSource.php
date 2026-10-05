<?php

namespace App\Service\Seo\Source;

use App\Entity\Site;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogCategoryRepository;
use App\Repository\LocaleRepository;
use App\Routing\ShopRoutes;
use App\Service\Seo\SitemapSourceInterface;
use App\Service\Seo\SitemapUrlGroup;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BlogSitemapSource implements SitemapSourceInterface
{
    public function __construct(
        private readonly BlogArticleRepository $articleRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function collect(?Site $site, array $locales): array
    {
        if ($site === null || !\in_array(ShopRoutes::SITEMAP_LOCALE, $locales, true)) {
            return [];
        }

        $locale = $this->localeRepository->findOneBy(['code' => ShopRoutes::SITEMAP_LOCALE]);
        if ($locale === null) {
            return [];
        }

        $groups = [];
        $localeCode = ShopRoutes::SITEMAP_LOCALE;

        $articles = $this->articleRepository->findPublishedBySiteAndLocale($site, $locale);
        $latestArticleAt = null;
        foreach ($articles as $article) {
            $publishedAt = $article->getPublishedAt();
            $updatedAt = $article->getUpdatedAt();
            $lastmod = $updatedAt ?? $publishedAt;
            if ($lastmod !== null && ($latestArticleAt === null || $lastmod > $latestArticleAt)) {
                $latestArticleAt = $lastmod;
            }
        }

        $groups[] = new SitemapUrlGroup(
            loc: $this->urlGenerator->generate(
                'shop_blog_index',
                ['_locale' => $localeCode],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            lastmod: $latestArticleAt,
            changefreq: 'daily',
            priority: '0.8',
        );

        $groups[] = new SitemapUrlGroup(
            loc: $this->urlGenerator->generate(
                'shop_blog_search',
                ['_locale' => $localeCode],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            lastmod: $latestArticleAt,
            changefreq: 'weekly',
            priority: '0.4',
        );

        $groups[] = new SitemapUrlGroup(
            loc: $this->urlGenerator->generate(
                'shop_blog_archive',
                ['_locale' => $localeCode],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            lastmod: $latestArticleAt,
            changefreq: 'weekly',
            priority: '0.5',
        );

        foreach ($this->articleRepository->findPublishedMonthBuckets($site, $locale) as $bucket) {
            $groups[] = new SitemapUrlGroup(
                loc: $this->urlGenerator->generate(
                    'shop_blog_archive_month',
                    [
                        '_locale' => $localeCode,
                        'year' => $bucket['year'],
                        'month' => sprintf('%02d', $bucket['month']),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                lastmod: $latestArticleAt,
                changefreq: 'monthly',
                priority: '0.5',
            );
        }

        foreach ($this->categoryRepository->findEnabledBySiteAndLocale($site, $locale) as $category) {
            $groups[] = new SitemapUrlGroup(
                loc: $this->urlGenerator->generate(
                    'shop_blog_category',
                    ['_locale' => $localeCode, 'slug' => $category->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                lastmod: $category->getUpdatedAt() ?? $latestArticleAt,
                changefreq: 'weekly',
                priority: '0.6',
            );
        }

        foreach ($articles as $article) {
            $groups[] = new SitemapUrlGroup(
                loc: $this->urlGenerator->generate(
                    'shop_blog_article',
                    ['_locale' => $localeCode, 'slug' => $article->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                lastmod: $article->getUpdatedAt() ?? $article->getPublishedAt(),
                changefreq: 'monthly',
                priority: '0.7',
            );
        }

        return $groups;
    }
}
