<?php

namespace App\Service\Seo\Source;

use App\Entity\Site;
use App\Routing\ShopRoutes;
use App\Service\Seo\SitemapSourceInterface;
use App\Service\Seo\SitemapUrlGroup;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HomepageSitemapSource implements SitemapSourceInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function collect(?Site $site, array $locales): array
    {
        if (!\in_array(ShopRoutes::SITEMAP_LOCALE, $locales, true)) {
            return [];
        }

        return [
            new SitemapUrlGroup(
                loc: $this->urlGenerator->generate(
                    'shop_home',
                    ['_locale' => ShopRoutes::SITEMAP_LOCALE],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                lastmod: $site?->getUpdatedAt(),
                changefreq: 'daily',
                priority: '1.0',
            ),
        ];
    }
}
