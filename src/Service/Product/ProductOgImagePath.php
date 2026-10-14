<?php

namespace App\Service\Product;

/**
 * Product OG images: /uploads/images/og-images/{first}/{second}/{filename}
 * DB may store filename only or the relative shard path.
 */
final class ProductOgImagePath
{
    public const WEB_BASE = '/uploads/images/og-images';

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

        return basename($value) !== '.' && basename($value) !== '..' ? basename($value) : '';
    }

    public static function shardDir(string $filename): string
    {
        $filename = self::filename($filename);
        $first = self::sanitizeShardChar(mb_substr($filename, 0, 1));
        $second = self::sanitizeShardChar(mb_substr($filename, 1, 1));

        return $first.'/'.$second;
    }

    /**
     * Path relative to /uploads/images/og-images, e.g. h/o/homeware-5-TR_og.jpg
     */
    public static function relativePath(?string $value): string
    {
        $filename = self::filename($value);
        if ($filename === '') {
            return '';
        }

        return self::shardDir($filename).'/'.$filename;
    }

    /**
     * Public URL path: /uploads/images/og-images/{a}/{b}/{file}
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

        if (str_starts_with($value, self::WEB_BASE.'/')) {
            return $value;
        }

        // Already relative shard path: a/b/file.jpg
        if (preg_match('#^[A-Za-z0-9._+-]/[A-Za-z0-9._+-]/[^/]+$#', $value) === 1) {
            return self::WEB_BASE.'/'.$value;
        }

        $relative = self::relativePath($value);

        return $relative !== '' ? self::WEB_BASE.'/'.$relative : '';
    }

    public static function absolutePath(string $projectDir, ?string $value): string
    {
        $relative = self::relativePath($value);
        if ($relative === '') {
            return '';
        }

        return rtrim($projectDir, '/').'/public'.self::WEB_BASE.'/'.$relative;
    }

    /**
     * Move a flat upload under og-images/ into the shard folder when needed.
     */
    public static function ensureStored(string $projectDir, ?string $value): void
    {
        $filename = self::filename($value);
        if ($filename === '') {
            return;
        }

        $target = self::absolutePath($projectDir, $filename);
        if ($target === '' || is_file($target)) {
            return;
        }

        $targetDir = \dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Cannot create OG image directory: '.$targetDir);
        }

        $publicRoot = rtrim($projectDir, '/').'/public'.self::WEB_BASE;
        $legacyFlat = rtrim($projectDir, '/').'/public/uploads/images/'.$filename;
        $candidates = [
            $publicRoot.'/'.$filename,
            $legacyFlat,
        ];

        foreach ($candidates as $source) {
            if (!is_file($source) || $source === $target) {
                continue;
            }
            if (!rename($source, $target) && !copy($source, $target)) {
                throw new \RuntimeException(sprintf('Cannot move OG image %s → %s', $source, $target));
            }
            if (is_file($source) && $source !== $target) {
                @unlink($source);
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
