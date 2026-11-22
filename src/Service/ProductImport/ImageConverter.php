<?php

namespace App\Service\ProductImport;

use Imagick;
use ImagickException;
use RuntimeException;

class ImageConverter
{
    public function isWebpPath(string $path): bool
    {
        return (bool)preg_match('/\.(jpe?g|png|jpg)$/i', $path);
    }

    public function isConvertiblePath(string $path): bool
    {
        return (bool)preg_match('/\.(jpe?g|png|jpg)$/i', $path);
    }

    /**
     * Replace .jpg / .jpeg / .png extension with .webp (same basename, no resize).
     */
    public function toWebpPath(string $source): string
    {
        return (string)preg_replace('/\.(jpe?g|png)$/i', '.webp', $source);
    }

    /**
     * Ensure a same-size WebP sibling exists and return its absolute path.
     * Returns null when conversion is not possible.
     * If the JPG/PNG is missing but an existing .webp sibling is present, that path is returned.
     */
    public function ensureWebp(string $source, bool $dryRun = false): ?string
    {
        if (preg_match('/\.webp$/i', $source)) {
            return is_file($source) ? $source : null;
        }

        if (!$this->isConvertiblePath($source)) {
            return null;
        }

        $target = $this->toWebpPath($source);
        if (!is_file($source) && is_file($target)) {
            return $target;
        }

        $result = $this->convert($source, $dryRun);
        $status = $result['status'] ?? 'error';

        if (in_array($status, ['converted', 'exists', 'would_convert'], true)) {
            return $result['target'] ?? $target;
        }

        return null;
    }

    /**
     * Convert JPG/PNG to WebP without changing dimensions.
     * Output filename: image.jpg -> image.webp
     *
     * @return array{status: string, target?: string, source?: string, message?: string, source_bytes?: int|false, webp_bytes?: int|false, saved_bytes?: int, saved_percent?: int}
     */
    public function convert(string $source, bool $dryRun = false): array
    {
        if (!is_file($source) || !is_readable($source)) {
            return ['status' => 'missing'];
        }

        if (!$this->isConvertiblePath($source)) {
            return ['status' => 'unsupported'];
        }

        $info = @getimagesize($source);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return ['status' => 'unsupported'];
        }

        $target = $this->toWebpPath($source);

        if ($target === $source) {
            return ['status' => 'unsupported'];
        }

        if (is_file($target) && filemtime($target) >= filemtime($source)) {
            return ['status' => 'exists', 'target' => $target, 'source' => $source];
        }

        $quality = 80;
        $temporary = $target . '.tmp';
        try {
            if (extension_loaded('gd') && function_exists('imagewebp')) {
                $this->convertWithGd($source, $temporary, $quality, (int)$info[2]);
            } elseif (extension_loaded('imagick')) {
                $this->convertWithImagick($source, $temporary, $quality);
            } else {
                return ['status' => 'no_encoder'];
            }

            clearstatcache(true, $temporary);
            if (!is_file($temporary) || filesize($temporary) === 0) {
                throw new RuntimeException('WebP encoder produced an empty file.');
            }

            $sourceBytes = filesize($source);
            $targetBytes = filesize($temporary);
            $savingPercent = $sourceBytes > 0 ? (int)round((1 - ($targetBytes / $sourceBytes)) * 100) : 0;

            if (!@rename($temporary, $target)) {
                throw new RuntimeException('Unable to move generated WebP file into place.');
            }

            return [
                'status' => 'converted',
                'source' => $source,
                'target' => $target,
                'source_bytes' => $sourceBytes,
                'webp_bytes' => $targetBytes,
                'saved_bytes' => max(0, (int)$sourceBytes - (int)$targetBytes),
                'saved_percent' => $savingPercent,
            ];
        } catch (\Throwable $exception) {
            @unlink($temporary);
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    /**
     * @throws ImagickException
     */
    private function convertWithImagick(string $source, string $target, int $quality): void
    {
        $image = new Imagick($source);
        $image->setIteratorIndex(0);
        if (method_exists($image, 'autoOrient')) {
            $image->autoOrient();
        }
        $image->stripImage();
        $image->setOption('webp:lossless', 'true');
        $image->setOption('webp:method', '6');
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->writeImage($target);
        $image->clear();
    }

    private function convertWithGd(string $source, string $target, int $quality, int $type): void
    {
        $image = $type === IMAGETYPE_JPEG ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
        if (!$image) {
            throw new RuntimeException('GD could not decode the source image.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $output = imagecreatetruecolor($width, $height);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($output, false);
            imagesavealpha($output, true);
            $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
            imagefilledrectangle($output, 0, 0, $width, $height, $transparent);
            imagealphablending($output, true);
        }

        imagecopy($output, $image, 0, 0, 0, 0, $width, $height);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($output, false);
            imagesavealpha($output, true);
        }

        if (!imagewebp($output, $target, $quality)) {
            imagedestroy($image);
            imagedestroy($output);
            throw new RuntimeException('GD failed to encode WebP.');
        }

        imagedestroy($image);
        imagedestroy($output);
    }

    public function convertFile(string $sourcePath, string $resultPath, int $quality): array|int
    {
        $info = @getimagesize($sourcePath);
        $temporary = $sourcePath . '.tmp';
        try {
            if (extension_loaded('gd') && function_exists('imagewebp')) {
                $this->convertWithGd($sourcePath, $temporary, $quality, (int)$info[2]);
            } elseif (extension_loaded('imagick')) {
                $this->convertWithImagick($sourcePath, $temporary, $quality);
            } else {
                return ['status' => 'no_encoder'];
            }

            clearstatcache(true, $temporary);
            if (!is_file($temporary) || filesize($temporary) === 0) {
                throw new RuntimeException('WebP encoder produced an empty file.');
            }
            $sourceBytes = filesize($sourcePath);
            $targetBytes = filesize($temporary);
            $savingPercent = $sourceBytes > 0 ? (int)round((1 - ($targetBytes / $sourceBytes)) * 100) : 0;
            if (!@rename($temporary, $resultPath)) {
                throw new RuntimeException('Unable to move generated WebP file into place.');
            }
            return $savingPercent;
        } catch (\Throwable $exception) {
            @unlink($temporary);
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }
}
