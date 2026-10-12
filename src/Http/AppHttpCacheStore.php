<?php

namespace App\Http;

use App\Service\GeoIp\UserCityService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\Store;

/**
 * The page header shows the visitor's delivery city, so the city cookie is part of the cache key
 * (same as the nginx fastcgi_cache_key in docs/nginx/fpc.conf).
 */
final class AppHttpCacheStore extends Store
{
    protected function generateCacheKey(Request $request): string
    {
        $city = (string) $request->cookies->get(UserCityService::COOKIE_DATA_NAME, '');

        return 'md' . hash('sha256', $request->getUri() . "\0" . $city);
    }
}
