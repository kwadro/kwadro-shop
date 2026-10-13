<?php

namespace App\Service\Product;

use Doctrine\DBAL\Connection;

/**
 * Imported SKUs are stored as "<supplier-slug>-<code>"; storefront shows only "<code>".
 */
final class SkuDisplayFormatter
{
    private ?string $prefixPattern = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function format(?string $sku): string
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return '';
        }

        $pattern = $this->prefixPattern ??= $this->buildPrefixPattern();
        if ($pattern === '') {
            return $sku;
        }

        $stripped = preg_replace($pattern, '', $sku, 1);

        return \is_string($stripped) && $stripped !== '' ? $stripped : $sku;
    }

    private function buildPrefixPattern(): string
    {
        try {
            $slugs = $this->connection->fetchFirstColumn(
                "SELECT slug FROM shop_supplier WHERE slug IS NOT NULL AND slug <> ''",
            );
        } catch (\Throwable) {
            return '';
        }

        $slugs = array_values(array_filter(array_map('strval', $slugs)));
        if ($slugs === []) {
            return '';
        }

        // Longest first so "mahazyn-kvadro2-" wins over "mahazyn-kvadro-".
        usort($slugs, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return '/^(?:' . implode('|', array_map(static fn (string $slug): string => preg_quote($slug, '/'), $slugs)) . ')-/iu';
    }
}
