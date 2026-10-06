<?php

namespace App\Service\Product;

/**
 * Product gallery files are stored as filenames in DB.
 * Physical/web path: /uploads/products/{first}/{second}/{filename}
 * Example: example.png → /uploads/products/e/x/example.png
 */
final class ProductImagePath
{
    public const WEB_BASE = '/uploads/products';

    public static function filename(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH);
            $value = \is_string($path) ? $path : $value;
        }

        $value = str_replace('\\', '/', $value);
        $value = basename($value);

        return $value !== '.' && $value !== '..' ? $value : '';
    }

    public static function shardDir(string $filename): string
    {
        $filename = self::filename($filename);
        $first = self::sanitizeShardChar(mb_substr($filename, 0, 1));
        $second = self::sanitizeShardChar(mb_substr($filename, 1, 1));

        return $first.'/'.$second;
    }

    /**
     * Relative path under /uploads/products used on disk and in admin previews.
     * Example: e/x/example.png
     */
    public static function relativePath(string $filename): string
    {
        $filename = self::filename($filename);
        if ($filename === '') {
            return '';
        }

        return self::shardDir($filename).'/'.$filename;
    }

    /**
     * Public URL path for templates/frontend.
     * Example: /uploads/products/e/x/example.png
     */
    public static function webPath(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $value = str_replace('\\', '/', $value);

        // Non-product uploads (logo, promo, etc.) stay unchanged.
        if (str_starts_with($value, '/uploads/') && !str_starts_with($value, self::WEB_BASE.'/')) {
            return $value;
        }

        $filename = self::filename($value);
        if ($filename === '') {
            return '';
        }

        return self::WEB_BASE.'/'.self::relativePath($filename);
    }

    /**
     * Absolute filesystem path under project public dir.
     */
    public static function absolutePath(string $projectDir, string $filename): string
    {
        $relative = self::relativePath($filename);
        if ($relative === '') {
            return '';
        }

        return rtrim($projectDir, '/').'/public'.self::WEB_BASE.'/'.$relative;
    }

    public static function ensureStored(string $projectDir, string $filename): void
    {
        $filename = self::filename($filename);
        if ($filename === '') {
            return;
        }

        $target = self::absolutePath($projectDir, $filename);
        if ($target === '' || is_file($target)) {
            return;
        }

        $targetDir = \dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Cannot create product image directory: '.$targetDir);
        }

        $candidates = [
            rtrim($projectDir, '/').'/public'.self::WEB_BASE.'/'.$filename,
            rtrim($projectDir, '/').'/data-product/image/'.$filename,
        ];

        foreach ($candidates as $source) {
            if (!is_file($source)) {
                continue;
            }
            if (!rename($source, $target) && !copy($source, $target)) {
                throw new \RuntimeException(sprintf('Cannot move product image %s → %s', $source, $target));
            }

            return;
        }
    }

    private static function sanitizeShardChar(string $char): string
    {
        $char = trim($char);
        if ($char === '' || $char === '.' || $char === '/' || $char === '\\') {
            return '_';
        }

        return $char;
    }
}
