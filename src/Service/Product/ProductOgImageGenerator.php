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

    /**
     * Category OG placeholder when no product photo is available.
     */
    public function writeLabelJpeg(string $title, string $targetPath, int $quality = 88): void
    {
        $this->writeCategoryFeatureJpeg($title, null, $targetPath, $quality, null);
    }

    /**
     * Category OG: lifestyle theme background + category title on the left + product on the right.
     * No brand mark, catalog caption, or green accent bars.
     */
    public function writeCategoryFeatureJpeg(
        string $title,
        ?string $sourcePath,
        string $targetPath,
        int $quality = 90,
        ?string $backgroundPath = null,
    ): void {
        $dst = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($dst === false) {
            throw new \RuntimeException('Cannot create OG canvas.');
        }

        imagealphablending($dst, true);

        $background = $backgroundPath !== null ? $this->loadImage($backgroundPath) : null;
        if ($background !== null) {
            $this->paintCoverPhoto($dst, $background, 0, 0, self::WIDTH, self::HEIGHT);
            imagedestroy($background);
        } else {
            $this->paintNeutralLifestyleBackdrop($dst);
        }

        // Soft veil so the large category title stays readable.
        // Category OG is lifestyle background + title only (product photo ignored).
        $this->paintLeftReadableVeil($dst);

        $fontBold = $this->resolveFontPath(true);
        $titleColor = imagecolorallocate($dst, 255, 255, 255);
        $textMaxW = 980;
        if ($fontBold !== null) {
            $firstBaseline = $this->drawWrappedTtf($dst, $title, $fontBold, 72, $titleColor, 64, 250, $textMaxW);
            // Short white rule under the first title line.
            $ruleY = $firstBaseline + 18;
            imagefilledrectangle($dst, 64, $ruleY, 280, $ruleY + 4, $titleColor);
        } else {
            imagestring($dst, 5, 64, 250, $this->asciiFallback($title), $titleColor);
        }

        $dir = \dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($dst);
            throw new \RuntimeException('Cannot create directory: '.$dir);
        }

        if (!imagejpeg($dst, $targetPath, $quality)) {
            imagedestroy($dst);
            throw new \RuntimeException('Cannot write OG image: '.$targetPath);
        }

        imagedestroy($dst);
    }

    private function paintNeutralLifestyleBackdrop(\GdImage $dst): void
    {
        // Warm dark interior gradient (no green brand accents).
        for ($y = 0; $y < self::HEIGHT; ++$y) {
            $t = $y / (self::HEIGHT - 1);
            $r = (int) round(42 + (28 - 42) * $t);
            $g = (int) round(40 + (30 - 40) * $t);
            $b = (int) round(38 + (32 - 38) * $t);
            $color = imagecolorallocate($dst, $r, $g, $b);
            imageline($dst, 0, $y, self::WIDTH, $y, $color);
        }

        $warm = imagecolorallocatealpha($dst, 180, 140, 90, 112);
        imagefilledellipse($dst, 980, 120, 520, 360, $warm);
        $cool = imagecolorallocatealpha($dst, 70, 90, 110, 115);
        imagefilledellipse($dst, 180, 520, 420, 300, $cool);
    }

    private function paintLeftReadableVeil(\GdImage $dst): void
    {
        $veilWidth = 820;
        for ($x = 0; $x < $veilWidth; ++$x) {
            $t = $x / ($veilWidth - 1);
            // Stronger on the left, fades to transparent.
            $alpha = (int) round(36 + (127 - 36) * $t);
            $alpha = max(0, min(127, $alpha));
            $color = imagecolorallocatealpha($dst, 12, 14, 18, $alpha);
            imageline($dst, $x, 0, $x, self::HEIGHT, $color);
        }
    }

    private function paintContainPhoto(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1 || $w < 1 || $h < 1) {
            return;
        }

        $scale = min($w / $sw, $h / $sh);
        $tw = max(1, (int) round($sw * $scale));
        $th = max(1, (int) round($sh * $scale));
        $tx = $x + (int) (($w - $tw) / 2);
        $ty = $y + (int) (($h - $th) / 2);
        imagecopyresampled($dst, $src, $tx, $ty, 0, 0, $tw, $th, $sw, $sh);
    }

    /**
     * Draw a product photo on the right without a white card: key out studio white,
     * then place with a soft drop shadow.
     */
    private function paintFloatingProduct(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $cutout = $this->createWhiteKeyedTruecolor($src);
        if ($cutout === null) {
            return;
        }

        $cw = imagesx($cutout);
        $ch = imagesy($cutout);
        if ($cw < 1 || $ch < 1) {
            imagedestroy($cutout);

            return;
        }

        $scale = min($w / $cw, $h / $ch);
        $tw = max(1, (int) round($cw * $scale));
        $th = max(1, (int) round($ch * $scale));
        $scaled = imagecreatetruecolor($tw, $th);
        if ($scaled === false) {
            imagedestroy($cutout);

            return;
        }
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefilledrectangle($scaled, 0, 0, $tw, $th, $transparent);
        imagealphablending($scaled, true);
        imagecopyresampled($scaled, $cutout, 0, 0, 0, 0, $tw, $th, $cw, $ch);
        imagedestroy($cutout);

        $tx = $x + (int) (($w - $tw) / 2);
        $ty = $y + (int) (($h - $th) / 2);

        // Soft contact shadow under the product (many faint ellipses ≈ blur).
        $cx = $tx + (int) ($tw / 2) + 6;
        $cy = $ty + $th - 12;
        for ($i = 14; $i >= 1; --$i) {
            $t = $i / 14;
            $color = imagecolorallocatealpha($dst, 12, 14, 18, (int) round(100 + 22 * $t));
            imagefilledellipse(
                $dst,
                $cx,
                $cy,
                max(28, (int) ($tw * (0.42 + 0.48 * $t))),
                max(10, (int) ($th * (0.05 + 0.14 * $t))),
                $color,
            );
        }

        imagecopy($dst, $scaled, $tx, $ty, 0, 0, $tw, $th);
        imagedestroy($scaled);
    }

    /**
     * Remove edge-connected studio white so the product floats (no white card / halo).
     * Only pixels reachable from the image border are keyed — white product areas stay.
     */
    private function createWhiteKeyedTruecolor(\GdImage $src): ?\GdImage
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            return null;
        }

        $out = imagecreatetruecolor($sw, $sh);
        if ($out === false) {
            return null;
        }
        imagealphablending($out, false);
        imagesavealpha($out, true);

        /** @var array<int, bool> $bg */
        $bg = [];
        $queue = [];
        $push = static function (int $x, int $y) use (&$queue, &$bg, $sw, $sh): void {
            if ($x < 0 || $y < 0 || $x >= $sw || $y >= $sh) {
                return;
            }
            $i = $y * $sw + $x;
            if (isset($bg[$i])) {
                return;
            }
            $bg[$i] = true;
            $queue[] = $i;
        };

        for ($x = 0; $x < $sw; ++$x) {
            $push($x, 0);
            $push($x, $sh - 1);
        }
        for ($y = 0; $y < $sh; ++$y) {
            $push(0, $y);
            $push($sw - 1, $y);
        }

        $qi = 0;
        while ($qi < \count($queue)) {
            $i = $queue[$qi++];
            $x = $i % $sw;
            $y = intdiv($i, $sw);
            $rgba = imagecolorat($src, $x, $y);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            if (!$this->isStudioBackdropTone($r, $g, $b)) {
                unset($bg[$i]);
                continue;
            }
            $push($x + 1, $y);
            $push($x - 1, $y);
            $push($x, $y + 1);
            $push($x, $y - 1);
        }

        // Dilate background mask slightly to eat white fringe around the cutout.
        $dilated = $bg;
        foreach ($bg as $i => $_) {
            $x = $i % $sw;
            $y = intdiv($i, $sw);
            for ($dy = -1; $dy <= 1; ++$dy) {
                for ($dx = -1; $dx <= 1; ++$dx) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $sw || $ny >= $sh) {
                        continue;
                    }
                    $dilated[$ny * $sw + $nx] = true;
                }
            }
        }

        for ($yy = 0; $yy < $sh; ++$yy) {
            for ($xx = 0; $xx < $sw; ++$xx) {
                $i = $yy * $sw + $xx;
                $rgba = imagecolorat($src, $xx, $yy);
                $a = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if (isset($bg[$i])) {
                    $color = imagecolorallocatealpha($out, 0, 0, 0, 127);
                } elseif (isset($dilated[$i]) && $this->isSoftStudioFringe($r, $g, $b)) {
                    // Semi-transparent edge: pull residual white out of the pixel.
                    [$r, $g, $b, $alpha] = $this->decontaminateWhiteFringe($r, $g, $b);
                    $color = imagecolorallocatealpha($out, $r, $g, $b, $alpha);
                } else {
                    $color = imagecolorallocatealpha($out, $r, $g, $b, $a);
                }
                imagesetpixel($out, $xx, $yy, $color);
            }
        }

        return $out;
    }

    private function isStudioBackdropTone(int $r, int $g, int $b): bool
    {
        $min = min($r, $g, $b);
        $max = max($r, $g, $b);
        $avg = ($r + $g + $b) / 3;

        return ($min >= 228 && ($max - $min) <= 22)
            || ($avg >= 236 && ($max - $min) <= 28);
    }

    private function isSoftStudioFringe(int $r, int $g, int $b): bool
    {
        $min = min($r, $g, $b);
        $max = max($r, $g, $b);
        $avg = ($r + $g + $b) / 3;

        return $avg >= 195 && ($max - $min) <= 36 && $min >= 175;
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private function decontaminateWhiteFringe(int $r, int $g, int $b): array
    {
        $avg = ($r + $g + $b) / 3.0;
        $t = max(0.0, min(1.0, ($avg - 195) / 60));
        $alpha = (int) round(35 + (127 - 35) * $t);
        // Estimate and remove white mixed into the edge pixel.
        $white = min($r, $g, $b);
        $mix = max(0.0, min(0.85, ($white - 170) / 85));
        $den = max(0.15, 1.0 - $mix);
        $r = (int) max(0, min(255, round(($r - 255 * $mix) / $den)));
        $g = (int) max(0, min(255, round(($g - 255 * $mix) / $den)));
        $b = (int) max(0, min(255, round(($b - 255 * $mix) / $den)));

        return [$r, $g, $b, $alpha];
    }

    private function paintSoftShadow(\GdImage $dst, int $x, int $y, int $w, int $h): void
    {
        for ($i = 12; $i >= 1; --$i) {
            $alpha = 115 + $i;
            $color = imagecolorallocatealpha($dst, 30, 40, 50, min(127, $alpha));
            imagefilledrectangle($dst, $x - $i, $y - $i, $x + $w + $i, $y + $h + $i, $color);
        }
    }

    private function paintCoverPhoto(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $tw = max(1, (int) round($sw * $scale));
        $th = max(1, (int) round($sh * $scale));
        $tmp = imagecreatetruecolor($tw, $th);
        if ($tmp === false) {
            return;
        }
        $white = imagecolorallocate($tmp, 255, 255, 255);
        imagefilledrectangle($tmp, 0, 0, $tw, $th, $white);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);

        $sx = (int) max(0, ($tw - $w) / 2);
        $sy = (int) max(0, ($th - $h) / 2);
        imagecopy($dst, $tmp, $x, $y, $sx, $sy, $w, $h);
        imagedestroy($tmp);
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

    public static function categoryOgFilename(string $slug): string
    {
        $stem = trim($slug);
        $stem = preg_replace('/\s+/u', '-', $stem) ?? $stem;
        $stem = preg_replace('/[^A-Za-z0-9._+-]+/u', '-', $stem) ?? $stem;
        $stem = trim($stem, '.-_');
        if ($stem === '') {
            $stem = 'category';
        }
        if (mb_strlen($stem) > 100) {
            $stem = mb_substr($stem, 0, 100);
        }

        return 'og-category-'.$stem.'.jpg';
    }

    private function resolveFontPath(bool $bold = true): ?string
    {
        $candidates = $bold
            ? [
                '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
            ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return int Baseline Y of the first drawn line */
    private function drawWrappedTtf(\GdImage $dst, string $text, string $font, float $size, int $color, int $x, int $y, int $maxWidth): int
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $trial = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $trial);
            $width = $box !== false ? (int) abs($box[2] - $box[0]) : 0;
            if ($current !== '' && $width > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $trial;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        $lineHeight = (int) round($size * 1.35);
        $maxLines = 3;
        $firstBaseline = $y;
        foreach (\array_slice($lines, 0, $maxLines) as $i => $line) {
            if ($i === $maxLines - 1 && \count($lines) > $maxLines) {
                $line = rtrim(mb_substr($line, 0, max(1, mb_strlen($line) - 1))).'…';
            }
            $baseline = $y + ($i * $lineHeight);
            if ($i === 0) {
                $firstBaseline = $baseline;
            }
            imagettftext($dst, $size, 0, $x, $baseline, $color, $font, $line);
        }

        return $firstBaseline;
    }

    private function asciiFallback(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'Category';
        }

        return mb_strlen($text) > 40 ? mb_substr($text, 0, 39).'…' : $text;
    }
}
