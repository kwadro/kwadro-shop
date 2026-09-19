<?php

namespace App\Service\Seo;

use App\Entity\Site;

interface SitemapSourceInterface
{
    /**
     * @param list<string> $locales
     *
     * @return list<SitemapUrlGroup>
     */
    public function collect(?Site $site, array $locales): array;
}
