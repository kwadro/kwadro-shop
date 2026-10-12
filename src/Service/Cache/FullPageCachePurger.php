<?php

namespace App\Service\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Clears the whole full page cache directory; entries are re-created on the next requests.
 * nginx mode: FPC_CACHE_DIR (fastcgi_cache_path), PHP-FPM must be allowed to delete its files.
 * symfony mode: var/cache/<env>/http_cache unless FPC_CACHE_DIR is set.
 */
final class FullPageCachePurger
{
    private bool $pending = false;
    private readonly string $cacheDir;

    public function __construct(
        #[Autowire('%env(FPC_CACHE_DIR)%')]
        string $cacheDir,
        #[Autowire('%env(FPC_MODE)%')]
        string $mode,
        #[Autowire('%kernel.cache_dir%')]
        string $kernelCacheDir,
        private readonly LoggerInterface $logger,
    ) {
        $cacheDir = trim($cacheDir);
        if ($cacheDir === '' && $mode === FullPageCachePolicy::MODE_SYMFONY) {
            $cacheDir = $kernelCacheDir . '/http_cache';
        }
        $this->cacheDir = $cacheDir;
    }

    public function isConfigured(): bool
    {
        return trim($this->cacheDir) !== '';
    }

    public function schedule(): void
    {
        $this->pending = true;
    }

    public function purgeIfScheduled(): void
    {
        if ($this->pending) {
            $this->purge();
        }
    }

    /** @return int number of removed cache files, -1 when the cache dir is not configured or missing */
    public function purge(): int
    {
        $this->pending = false;

        $dir = rtrim(trim($this->cacheDir), '/');
        if ($dir === '' || $dir === '.' || !is_dir($dir)) {
            return -1;
        }

        $removed = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($path);
                continue;
            }
            if (@unlink($path)) {
                ++$removed;
            } else {
                $this->logger->warning('FPC purge: cannot remove {path}', ['path' => $path]);
            }
        }

        return $removed;
    }
}
