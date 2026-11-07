<?php

namespace App\Service\ProductImport;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Exclusive lock so only one process imports a given feed at a time.
 * The handle must stay open for the whole run; closing it releases the lock.
 */
final class ProductImportRunLock
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function isBusy(int $importId): bool
    {
        $path = $this->path($importId);
        if (!is_file($path)) {
            return false;
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return false;
        }

        $busy = !flock($handle, LOCK_EX | LOCK_NB);
        if (!$busy) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return $busy;
    }

    /**
     * @return resource|null
     */
    public function acquire(int $importId)
    {
        $path = $this->path($importId);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $handle = fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (\is_resource($handle)) {
                fclose($handle);
            }

            return null;
        }

        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid());
        fflush($handle);

        return $handle;
    }

    /**
     * @param resource $handle
     */
    public function release($handle): void
    {
        if (!\is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function path(int $importId): string
    {
        return $this->projectDir.'/var/import/product-import-'.$importId.'.lock';
    }
}
