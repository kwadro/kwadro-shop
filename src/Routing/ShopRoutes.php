<?php

namespace App\Routing;

final class ShopRoutes
{
    public const LOCALE_REQUIREMENTS = 'ua|en';

    /** Default shop locale: homepage is served at / (no prefix). */
    public const DEFAULT_LOCALE = 'ua';

    public const SITEMAP_LOCALE = self::DEFAULT_LOCALE;

    /** Former URL locale prefix; kept for permanent redirects to ua. */
    public const LEGACY_LOCALE_PREFIX = 'uk';

    /** Hyphen and underscore allowed. */
    public const SLUG_REQUIREMENTS = '[a-z0-9][a-z0-9_-]*';

    /** Reserved exact slugs that must not collide with shop routes / locales. */
    public const RESERVED_PAGE_SLUGS = 'login|logout|register|invite|admin|checkout|admser|category|product|supplier|blog|search|city|ua|uk|en';

    /** Menu / static page slugs: same charset as SLUG_REQUIREMENTS, minus reserved names. */
    public const PAGE_SLUG_REQUIREMENTS = '(?!(?:'.self::RESERVED_PAGE_SLUGS.')$)'.self::SLUG_REQUIREMENTS;

    /** @var list<string> */
    public const INDEXABLE_MENU_TYPES = ['Link', 'FooterLink'];

    /** @var list<string> */
    public const SITEMAP_EXCLUDED_URLS = [
        'homepage',
        'about',
        'holiday_table',
        'account_setting',
        'app_logout',
    ];
}
