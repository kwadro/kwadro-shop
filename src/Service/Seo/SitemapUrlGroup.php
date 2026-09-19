<?php

namespace App\Service\Seo;

final class SitemapUrlGroup
{
    public function __construct(
        public readonly string $loc,
        public readonly ?\DateTimeImmutable $lastmod,
        public readonly string $changefreq,
        public readonly string $priority,
    ) {
    }
}
