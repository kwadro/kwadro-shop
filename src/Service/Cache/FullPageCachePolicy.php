<?php

namespace App\Service\Cache;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Which storefront requests may be stored in the full page cache.
 * Modes: "symfony" (PHP HttpCache, works on any hosting) or "nginx" (fastcgi_cache, see docs/nginx/fpc.conf).
 * Keep CART_COOKIE and SESSION_COOKIE in sync with docs/nginx/fpc.conf.
 */
final class FullPageCachePolicy
{
    public const CART_COOKIE = 'kv_cart';
    public const SESSION_COOKIE = 'PHPSESSID';
    public const MODE_SYMFONY = 'symfony';
    public const MODE_NGINX = 'nginx';

    /** @var list<string> */
    private const CACHEABLE_ROUTES = [
        'shop_home',
        'shop_home_trailing_slash',
        'shop_category',
        'shop_product',
        'shop_supplier',
        'shop_search',
        'shop_page',
        'shop_blog_index',
        'shop_blog_search',
        'shop_blog_archive',
        'shop_blog_archive_month',
        'shop_blog_category',
        'shop_blog_article',
    ];

    public function __construct(
        #[Autowire('%env(bool:FPC_ENABLED)%')]
        private readonly bool $enabled,
        #[Autowire('%env(int:FPC_TTL)%')]
        private readonly int $ttl,
        #[Autowire('%env(FPC_MODE)%')]
        private readonly string $mode = self::MODE_SYMFONY,
    ) {
    }

    /** Whether public/index.php should wrap the kernel in the PHP HttpCache. */
    public static function isSymfonyCacheEnabled(array $context): bool
    {
        $enabled = filter_var($context['FPC_ENABLED'] ?? '1', \FILTER_VALIDATE_BOOL);
        $mode = (string) ($context['FPC_MODE'] ?? self::MODE_SYMFONY);

        return $enabled
            && $mode === self::MODE_SYMFONY
            && ($context['APP_ENV'] ?? 'dev') === 'prod'
            && (int) ($context['FPC_TTL'] ?? 300) > 0;
    }

    /** Visitors with a session (logged in, checkout) or a non-empty cart always get a fresh page. */
    public static function requestBypassesCache(Request $request): bool
    {
        return $request->cookies->has(self::CART_COOKIE) || $request->cookies->has(self::SESSION_COOKIE);
    }

    public function isSymfonyMode(): bool
    {
        return $this->mode === self::MODE_SYMFONY;
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->ttl > 0;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function isCacheableRoute(Request $request): bool
    {
        return \in_array((string) $request->attributes->get('_route'), self::CACHEABLE_ROUTES, true);
    }

    /** Requests that may change the cart: the "cart has items" cookie is refreshed on them. */
    public function isCartRoute(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route');

        return str_starts_with($route, 'shop_cart_') || str_starts_with($route, 'shop_checkout');
    }

    public function hasBypassCookie(Request $request): bool
    {
        return self::requestBypassesCache($request) || $request->hasPreviousSession();
    }
}
