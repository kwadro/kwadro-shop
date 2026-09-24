<?php

namespace App\Routing;

final class ShopRoutes
{
    public const LOCALE_REQUIREMENTS = 'uk|en';

    public const SITEMAP_LOCALE = 'uk';

    /** Hyphen and underscore allowed. */
    public const SLUG_REQUIREMENTS = '[a-z0-9][a-z0-9_-]*';

    /** Reserved exact slugs that must not collide with shop routes / locales. */
    public const RESERVED_PAGE_SLUGS = 'login|logout|register|admin|checkout|admser|category|product|supplier|uk|en';

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
