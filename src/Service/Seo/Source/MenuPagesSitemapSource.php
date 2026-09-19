<?php

namespace App\Service\Seo\Source;

use App\Entity\Site;
use App\Repository\MegaMenuTranslationRepository;
use App\Routing\ShopRoutes;
use App\Service\Seo\SitemapSourceInterface;
use App\Service\Seo\SitemapUrlGroup;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MenuPagesSitemapSource implements SitemapSourceInterface
{
    public function __construct(
        private readonly MegaMenuTranslationRepository $menuTranslationRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function collect(?Site $site, array $locales): array
    {
        if (!$site instanceof Site || !\in_array(ShopRoutes::SITEMAP_LOCALE, $locales, true)) {
            return [];
        }

        $groups = [];

        foreach ($this->menuTranslationRepository->findPublishedSitemapEntries($site) as $row) {
            if ($row['locale'] !== ShopRoutes::SITEMAP_LOCALE || !$this->isPublicPageSlug($row['slug'])) {
                continue;
            }

            $groups[] = new SitemapUrlGroup(
                loc: $this->urlGenerator->generate(
                    'shop_page',
                    ['_locale' => ShopRoutes::SITEMAP_LOCALE, 'slug' => $row['slug']],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                lastmod: $row['updatedAt'] instanceof \DateTimeImmutable ? $row['updatedAt'] : null,
                changefreq: 'weekly',
                priority: '0.8',
            );
        }

        return $groups;
    }

    private function isPublicPageSlug(string $slug): bool
    {
        if (\in_array($slug, ShopRoutes::SITEMAP_EXCLUDED_URLS, true)) {
            return false;
        }

        return (bool) preg_match('/^'.ShopRoutes::PAGE_SLUG_REQUIREMENTS.'$/', $slug);
    }
}
