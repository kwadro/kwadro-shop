<?php

namespace App\Translation;

use App\Service\Intl\IntlLocaleMapper;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Keeps translator/routing locale as-is ("ua") but never sets ICU default to "ua".
 */
final class IntlSafeLocaleSwitcher extends LocaleSwitcher
{
    public function setLocale(string $locale): void
    {
        parent::setLocale($locale);
        IntlLocaleMapper::applyDefault();
    }
}
