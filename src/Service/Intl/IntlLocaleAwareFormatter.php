<?php

namespace App\Service\Intl;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Intl\IntlFormatterInterface;

/**
 * Decorates EasyAdmin Intl formatter so locale "ua" never reaches ICU.
 * Always passes an explicit ICU-safe locale (never null → never Locale::getDefault()="ua").
 */
final class IntlLocaleAwareFormatter implements IntlFormatterInterface
{
    public function __construct(
        private readonly IntlFormatterInterface $inner,
    ) {
    }

    public function formatCurrency(int|float $amount, string $currency, array $attrs = [], ?string $locale = null): string
    {
        IntlLocaleMapper::applyDefault();

        return $this->inner->formatCurrency(
            $amount,
            $currency,
            $attrs,
            IntlLocaleMapper::resolve($locale),
        );
    }

    public function formatNumber(int|float $number, array $attrs = [], string $style = 'decimal', string $type = 'default', ?string $locale = null): string
    {
        IntlLocaleMapper::applyDefault();

        return $this->inner->formatNumber(
            $number,
            $attrs,
            $style,
            $type,
            IntlLocaleMapper::resolve($locale),
        );
    }

    public function formatDateTime(?\DateTimeInterface $date, ?string $dateFormat = 'medium', ?string $timeFormat = 'medium', string $pattern = '', \DateTimeZone|string|bool|null $timezone = null, string $calendar = 'gregorian', ?string $locale = null): ?string
    {
        IntlLocaleMapper::applyDefault();

        return $this->inner->formatDateTime(
            $date,
            $dateFormat,
            $timeFormat,
            $pattern,
            $timezone,
            $calendar,
            IntlLocaleMapper::resolve($locale),
        );
    }

    public function formatDate(?\DateTimeInterface $date, ?string $dateFormat = 'medium', string $pattern = '', \DateTimeZone|string|bool|null $timezone = null, string $calendar = 'gregorian', ?string $locale = null): ?string
    {
        IntlLocaleMapper::applyDefault();

        return $this->inner->formatDate(
            $date,
            $dateFormat,
            $pattern,
            $timezone,
            $calendar,
            IntlLocaleMapper::resolve($locale),
        );
    }

    public function formatTime(?\DateTimeInterface $date, ?string $timeFormat = 'medium', string $pattern = '', \DateTimeZone|string|bool|null $timezone = null, string $calendar = 'gregorian', ?string $locale = null): ?string
    {
        IntlLocaleMapper::applyDefault();

        return $this->inner->formatTime(
            $date,
            $timeFormat,
            $pattern,
            $timezone,
            $calendar,
            IntlLocaleMapper::resolve($locale),
        );
    }
}
