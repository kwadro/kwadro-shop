<?php

namespace App\Routing;

final class ShopRoutes
{
    public const LOCALE_REQUIREMENTS = 'uk|en';

    public const SITEMAP_LOCALE = 'uk';

    public const PAGE_SLUG_REQUIREMENTS = '(?!login|logout|register|admin|checkout|admser|uk|en)[a-z0-9][a-z0-9\-]*';

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
