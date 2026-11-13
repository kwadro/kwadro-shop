<?php

namespace App\Http;

use App\Service\Intl\IntlLocaleMapper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps Symfony/translator locale as "ua" for routing and catalogues,
 * but never sets PHP ICU default to "ua" (IntlDateFormatter cannot construct it).
 */
final class IntlSafeRequest extends Request
{
    public function setDefaultLocale(string $locale): void
    {
        $this->defaultLocale = $locale;

        if (null === $this->locale) {
            $this->setIntlDefault($locale);
        }
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->setIntlDefault($locale);
    }

    private function setIntlDefault(string $locale): void
    {
        try {
            if (class_exists(\Locale::class, false)) {
                \Locale::setDefault(IntlLocaleMapper::resolve($locale));
            }
        } catch (\Exception) {
        }
    }
}
