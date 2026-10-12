<?php

namespace App\EventSubscriber;

use App\Service\Cache\FullPageCachePolicy;
use App\Service\Cart\CartStorageService;
use App\Service\Cart\VisitorIdResolver;
use App\Service\GeoIp\UserCityService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Marks anonymous storefront pages as cacheable for the full page cache
 * (X-Accel-Expires for nginx, Cache-Control s-maxage for the PHP HttpCache) and keeps the "cart has items" cookie in sync so nginx can bypass the cache for those visitors.
 */
class FullPageCacheSubscriber implements EventSubscriberInterface
{
    /** Default cookies that are safe to drop from cached pages: the next uncached request sets them. */
    private const DROPPABLE_COOKIES = [
        VisitorIdResolver::COOKIE_NAME,
        UserCityService::COOKIE_NAME,
        UserCityService::COOKIE_DATA_NAME,
    ];

    public function __construct(
        private readonly FullPageCachePolicy $policy,
        private readonly CartStorageService $cartStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the session listener (-1000), so a started session is already visible.
        return [KernelEvents::RESPONSE => ['onKernelResponse', -1100]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($this->policy->isCartRoute($request)) {
            $this->syncCartCookie($request, $response);
        }

        if (!$this->policy->isEnabled() || !$this->isCacheable($request, $response)) {
            $response->headers->set('X-Accel-Expires', '0');

            return;
        }

        foreach (self::DROPPABLE_COOKIES as $name) {
            $response->headers->removeCookie($name, '/');
        }

        $response->headers->set('X-Accel-Expires', (string) $this->policy->getTtl());
        $response->headers->set('X-FPC', 'cacheable');

        if ($this->policy->isSymfonyMode()) {
            // Browsers revalidate every time (max-age=0); only the server-side HttpCache keeps the page.
            $response->headers->set('Cache-Control', \sprintf('public, max-age=0, s-maxage=%d', $this->policy->getTtl()));
        }
    }

    private function isCacheable(Request $request, Response $response): bool
    {
        if (!$request->isMethodCacheable() || $response->getStatusCode() !== Response::HTTP_OK) {
            return false;
        }

        if (!$this->policy->isCacheableRoute($request) || $this->policy->hasBypassCookie($request)) {
            return false;
        }

        if ($request->hasSession() && $request->getSession()->isStarted()) {
            return false;
        }

        foreach ($response->headers->getCookies() as $cookie) {
            if (!\in_array($cookie->getName(), self::DROPPABLE_COOKIES, true)) {
                return false;
            }
        }

        return true;
    }

    private function syncCartCookie(Request $request, Response $response): void
    {
        $hasItems = (bool) ($this->cartStorage->getSummary()['hasItems'] ?? false);
        $hasCookie = $request->cookies->has(FullPageCachePolicy::CART_COOKIE);

        if ($hasItems && !$hasCookie) {
            $response->headers->setCookie(
                Cookie::create(FullPageCachePolicy::CART_COOKIE, '1')
                    ->withExpires(time() + 30 * 86400)
                    ->withPath('/')
                    ->withSecure($request->isSecure())
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX)
            );
        } elseif (!$hasItems && $hasCookie) {
            $response->headers->clearCookie(FullPageCachePolicy::CART_COOKIE, '/');
        }
    }
}
