<?php

namespace App\Service\Seo\Source;

use App\Entity\Site;
use App\Repository\SupplierRepository;
use App\Routing\ShopRoutes;
use App\Service\Seo\SitemapSourceInterface;
use App\Service\Seo\SitemapUrlGroup;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SupplierSitemapSource implements SitemapSourceInterface
{
    public function __construct(
        private readonly SupplierRepository $supplierRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function collect(?Site $site, array $locales): array
    {
        if (!\in_array(ShopRoutes::SITEMAP_LOCALE, $locales, true)) {
            return [];
        }

        $groups = [];

        foreach ($this->supplierRepository->findPublishedSitemapEntries() as $row) {
            $groups[] = new SitemapUrlGroup(
                loc: $this->urlGenerator->generate(
                    'shop_supplier',
                    ['_locale' => ShopRoutes::SITEMAP_LOCALE, 'slug' => $row['slug']],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                lastmod: $row['updatedAt'] instanceof \DateTimeImmutable ? $row['updatedAt'] : null,
                changefreq: 'weekly',
                priority: '0.65',
            );
        }

        return $groups;
    }
}
