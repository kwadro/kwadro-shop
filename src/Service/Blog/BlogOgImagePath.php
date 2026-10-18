<?php

namespace App\Service\Blog;

/**
 * Blog article OG images: /uploads/images/blog/{filename}
 * DB stores filename only (or legacy path); files live under blog/.
 */
final class BlogOgImagePath
{
    public const WEB_BASE = '/uploads/images/blog';
    public const DIR = 'public/uploads/images/blog';

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

    /**
     * Path relative to /uploads/images/blog, e.g. og-blog-yak-obraty-powerbank.jpg
     */
    public static function relativePath(?string $value): string
    {
        return self::filename($value);
    }

    /**
     * Public URL path: /uploads/images/blog/{file}
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

        $filename = self::filename($value);

        return $filename !== '' ? self::WEB_BASE.'/'.$filename : '';
    }

    public static function absolutePath(string $projectDir, ?string $value): string
    {
        $filename = self::filename($value);
        if ($filename === '') {
            return '';
        }

        return rtrim($projectDir, '/').'/public'.self::WEB_BASE.'/'.$filename;
    }

    /**
     * Move a legacy flat upload under uploads/images/ into blog/ when needed.
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
            throw new \RuntimeException('Cannot create blog OG image directory: '.$targetDir);
        }

        $legacyFlat = rtrim($projectDir, '/').'/public/uploads/images/'.$filename;
        foreach ([$legacyFlat] as $source) {
            if (!is_file($source) || $source === $target) {
                continue;
            }
            if (!rename($source, $target) && !copy($source, $target)) {
                throw new \RuntimeException(sprintf('Cannot move blog OG image %s → %s', $source, $target));
            }
            if (is_file($source) && $source !== $target) {
                @unlink($source);
            }

            return;
        }
    }
}
