<?php

namespace App\Service\Intl;

/**
 * Maps shop URL/translator locale "ua" to ICU language "uk".
 */
final class IntlLocaleMapper
{
    public static function toIntl(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return $locale;
        }

        $normalized = strtolower(str_replace('-', '_', $locale));
        if ($normalized === 'ua' || str_starts_with($normalized, 'ua_')) {
            return 'uk';
        }

        return $locale;
    }

    /**
     * Always returns a locale ICU can construct (never "ua", never empty).
     */
    public static function resolve(?string $locale = null): string
    {
        $raw = $locale;
        if ($raw === null || $raw === '') {
            $raw = \extension_loaded('intl') ? \Locale::getDefault() : 'en';
        }

        $mapped = self::toIntl($raw) ?? 'uk';
        if ($mapped === '' || strtolower(str_replace('-', '_', $mapped)) === 'ua'
            || str_starts_with(strtolower(str_replace('-', '_', $mapped)), 'ua_')) {
            return 'uk';
        }

        return $mapped;
    }

    public static function applyDefault(): void
    {
        if (!\extension_loaded('intl')) {
            return;
        }

        \Locale::setDefault(self::resolve(\Locale::getDefault()));
    }
}
