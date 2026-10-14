<?php

namespace App\Service\Product;

/**
 * Builds Facebook/Open Graph product images at 1200×630.
 */
final class ProductOgImageGenerator
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /**
     * Fit source into 1200×630 on a white canvas (contain), write JPEG.
     */
    public function writeContainJpeg(string $sourcePath, string $targetPath, int $quality = 88): void
    {
        $src = $this->loadImage($sourcePath);
        if ($src === null) {
            throw new \RuntimeException('Cannot read image: '.$sourcePath);
        }

        $dst = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($dst === false) {
            imagedestroy($src);
            throw new \RuntimeException('Cannot create OG canvas.');
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, self::WIDTH, self::HEIGHT, $white);

        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min(self::WIDTH / $sw, self::HEIGHT / $sh);
        $tw = max(1, (int) round($sw * $scale));
        $th = max(1, (int) round($sh * $scale));
        $tx = (int) ((self::WIDTH - $tw) / 2);
        $ty = (int) ((self::HEIGHT - $th) / 2);
        imagecopyresampled($dst, $src, $tx, $ty, 0, 0, $tw, $th, $sw, $sh);

        $dir = \dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($src);
            imagedestroy($dst);
            throw new \RuntimeException('Cannot create directory: '.$dir);
        }

        if (!imagejpeg($dst, $targetPath, $quality)) {
            imagedestroy($src);
            imagedestroy($dst);
            throw new \RuntimeException('Cannot write OG image: '.$targetPath);
        }

        imagedestroy($src);
        imagedestroy($dst);
    }

    public function loadImage(string $path): ?\GdImage
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        return match ($info[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            \IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            \IMAGETYPE_GIF => @imagecreatefromgif($path) ?: null,
            \IMAGETYPE_WEBP => \function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    /**
     * Safe filename stem from SKU for `{sku}_og.jpg`.
     */
    public static function ogImageFilename(string $sku, ?int $productId = null): string
    {
        $stem = trim($sku);
        $stem = preg_replace('/\s+/u', '-', $stem) ?? $stem;
        $stem = preg_replace('/[^A-Za-z0-9._+-]+/u', '-', $stem) ?? $stem;
        $stem = trim($stem, '.-_');
        if ($stem === '') {
            $stem = $productId !== null ? 'product-'.$productId : 'product';
        }

        // Keep filenames reasonable for shared hosting / Windows.
        if (mb_strlen($stem) > 120) {
            $stem = mb_substr($stem, 0, 120);
        }

        return $stem.'_og.jpg';
    }
}
