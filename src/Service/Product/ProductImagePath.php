<?php

namespace App\Service\Product;

/**
 * Product gallery files are stored as filenames in DB.
 * Physical/web path: /uploads/products/{supplier}/{first}/{second}/{filename}
 * Example: BZ02-21.png + mahazyn-kvadro → /uploads/products/mahazyn-kvadro/B/Z/BZ02-21.png
 */
final class ProductImagePath
{
    public const WEB_BASE = '/uploads/products';

    /**
     * Legacy / alternate supplier folder names that map to the current on-disk directory.
     * Lugi import historically used "lugi"; files now live under "homeware".
     *
     * @var array<string, string>
     */
    private const SUPPLIER_DIR_ALIASES = [
        'lugi' => 'homeware',
        'homeware-1' => 'homeware',
    ];

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

    public static function sanitizeSupplierCode(?string $supplierCode): string
    {
        $value = mb_strtolower(trim((string) $supplierCode));
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = trim($value, '-_');
        if ($value === '') {
            return '';
        }

        return self::SUPPLIER_DIR_ALIASES[$value] ?? $value;
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
     * Example: mahazyn-kvadro/B/Z/BZ02-21.png
     */
    public static function relativePath(string $filename, ?string $supplierCode = null): string
    {
        $filename = self::filename($filename);
        if ($filename === '') {
            return '';
        }

        $shardPath = self::shardDir($filename).'/'.$filename;
        $supplier = self::sanitizeSupplierCode($supplierCode);
        if ($supplier === '') {
            return $shardPath;
        }

        return $supplier.'/'.$shardPath;
    }

    /**
     * Public URL path for templates/frontend.
     * Example: /uploads/products/mahazyn-kvadro/B/Z/BZ02-21.png
     */
    public static function webPath(?string $value, ?string $supplierCode = null): string
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

        if (str_starts_with($value, self::WEB_BASE.'/')) {
            $rest = substr($value, \strlen(self::WEB_BASE) + 1);
            $parts = array_values(array_filter(explode('/', $rest), static fn (string $p): bool => $p !== ''));

            // Already supplier-scoped: {supplier}/{a}/{b}/{file}
            if (\count($parts) >= 4) {
                $filename = self::filename($parts[array_key_last($parts)]);
                $pathSupplier = self::sanitizeSupplierCode($parts[0]);
                $preferred = self::sanitizeSupplierCode($supplierCode);
                $supplier = $preferred !== '' ? $preferred : $pathSupplier;
                if ($filename !== '' && $supplier !== '') {
                    return self::WEB_BASE.'/'.self::relativePath($filename, $supplier);
                }

                if ($pathSupplier !== '' && $pathSupplier !== $parts[0]) {
                    $parts[0] = $pathSupplier;
                }

                return self::WEB_BASE.'/'.implode('/', $parts);
            }

            // Legacy sharded path: {a}/{b}/{file} — upgrade when supplier is known.
            if (\count($parts) === 3) {
                $filename = self::filename($parts[2]);
                $supplier = self::sanitizeSupplierCode($supplierCode);
                if ($filename !== '' && $supplier !== '') {
                    return self::WEB_BASE.'/'.self::relativePath($filename, $supplier);
                }

                return self::WEB_BASE.'/'.implode('/', $parts);
            }
        }

        $filename = self::filename($value);
        if ($filename === '') {
            return '';
        }

        $relative = self::relativePath($filename, $supplierCode);
        if ($relative === '') {
            return '';
        }

        return self::WEB_BASE.'/'.$relative;
    }

    /**
     * Absolute filesystem path under project public dir.
     */
    public static function absolutePath(string $projectDir, string $filename, ?string $supplierCode = null): string
    {
        $relative = self::relativePath($filename, $supplierCode);
        if ($relative === '') {
            return '';
        }

        return rtrim($projectDir, '/').'/public'.self::WEB_BASE.'/'.$relative;
    }

    public static function ensureStored(string $projectDir, string $filename, ?string $supplierCode = null): void
    {
        $filename = self::filename($filename);
        if ($filename === '') {
            return;
        }

        $target = self::absolutePath($projectDir, $filename, $supplierCode);
        if ($target === '' || is_file($target)) {
            return;
        }

        $targetDir = \dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Cannot create product image directory: '.$targetDir);
        }

        $publicRoot = rtrim($projectDir, '/').'/public'.self::WEB_BASE;
        $candidates = [
            // Legacy sharded path without supplier.
            $publicRoot.'/'.self::relativePath($filename, null),
            $publicRoot.'/'.$filename,
            rtrim($projectDir, '/').'/data-product/image/'.$filename,
        ];

        $supplier = self::sanitizeSupplierCode($supplierCode);
        if ($supplier !== '') {
            // Prefer already-correct supplier path if present under another layout.
            array_unshift($candidates, $publicRoot.'/'.$supplier.'/'.$filename);
        }

        foreach ($candidates as $source) {
            if (!is_file($source) || $source === $target) {
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
