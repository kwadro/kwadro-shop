<?php

namespace App\Http;

use App\Service\Cache\FullPageCachePolicy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * PHP full page cache for hosting without nginx/Varnish (e.g. Apache shared hosting).
 * Pages are stored in var/cache/<env>/http_cache; which pages are cacheable is decided
 * by FullPageCacheSubscriber via "Cache-Control: public, s-maxage=...".
 */
final class AppHttpCache extends HttpCache
{
    public function __construct(KernelInterface $kernel)
    {
        parent::__construct(
            $kernel,
            new AppHttpCacheStore(self::storeDir($kernel)),
            null,
            [
                'debug' => false,
                'trace_level' => 'short',
                'trace_header' => 'X-Symfony-Cache',
                'stale_if_error' => 600,
            ],
        );
    }

    public static function storeDir(KernelInterface $kernel): string
    {
        return $kernel->getCacheDir() . '/http_cache';
    }

    protected function lookup(Request $request, bool $catch = false): Response
    {
        if (FullPageCachePolicy::requestBypassesCache($request)) {
            return $this->pass($request, $catch);
        }

        return parent::lookup($request, $catch);
    }
}
