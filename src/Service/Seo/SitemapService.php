<?php

namespace App\Service\Seo;

use App\Repository\SiteRepository;
use App\Routing\ShopRoutes;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class SitemapService
{
    /**
     * @param iterable<SitemapSourceInterface> $sources
     */
    public function __construct(
        private readonly SiteRepository $siteRepository,
        #[TaggedIterator('app.sitemap_source')]
        private readonly iterable $sources,
    ) {
    }

    /**
     * @return list<array{
     *     loc: string,
     *     lastmod: \DateTimeImmutable|null,
     *     changefreq: string,
     *     priority: string
     * }>
     */
    public function buildEntries(string $domain): array
    {
        $site = $this->siteRepository->findOneBy(['domain' => $domain]);
        $entries = [];

        foreach ($this->sources as $source) {
            foreach ($source->collect($site, [ShopRoutes::SITEMAP_LOCALE]) as $group) {
                $entries[] = [
                    'loc' => $group->loc,
                    'lastmod' => $group->lastmod,
                    'changefreq' => $group->changefreq,
                    'priority' => $group->priority,
                ];
            }
        }

        return $this->deduplicateEntries($entries);
    }

    /**
     * @param list<array{
     *     loc: string,
     *     lastmod: \DateTimeImmutable|null,
     *     changefreq: string,
     *     priority: string
     * }> $entries
     *
     * @return list<array{
     *     loc: string,
     *     lastmod: \DateTimeImmutable|null,
     *     changefreq: string,
     *     priority: string
     * }>
     */
    private function deduplicateEntries(array $entries): array
    {
        $unique = [];

        foreach ($entries as $entry) {
            $unique[$entry['loc']] = $entry;
        }

        return array_values($unique);
    }
}
