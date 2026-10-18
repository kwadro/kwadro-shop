<?php

namespace App\Service\Image;

/**
 * Generates a new fixed-aspect image while preserving proportions.
 *
 * Background bands use the colour of the source edges, fading to a lighter
 * shade toward the outer frame (no photo fragments).
 * Subject: full source fitted with CONTAIN; position via alignX / alignY.
 */
final class BlurredBackgroundTransformService
{
    /**
     * @param array{
     *     width?: int,
     *     height?: int,
     *     blur?: int,
     *     alignX?: string,
     *     alignY?: string,
     *     rotate?: int,
     *     quality?: int
     * } $options
     */
    public function transformFile(string $sourcePath, string $destPath, array $options = []): void
    {
        $width = max(64, (int) ($options['width'] ?? 1080));
        $height = max(64, (int) ($options['height'] ?? 1920));
        $blur = max(0, min(40, (int) ($options['blur'] ?? 18)));
        $alignX = $this->normalizeAlign((string) ($options['alignX'] ?? 'center'), ['left', 'center', 'right'], 'center');
        $alignY = $this->normalizeAlign((string) ($options['alignY'] ?? 'center'), ['top', 'center', 'bottom'], 'center');
        $rotate = ((int) ($options['rotate'] ?? 0)) % 360;
        if ($rotate < 0) {
            $rotate += 360;
        }
        if (!\in_array($rotate, [0, 90, 180, 270], true)) {
            $rotate = 0;
        }
        $quality = max(60, min(95, (int) ($options['quality'] ?? 90)));

        $src = $this->loadImage($sourcePath);
        if ($rotate !== 0) {
            $rotated = $this->rotateImage($src, $rotate);
            imagedestroy($src);
            $src = $rotated;
        }
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($src);
            throw new \RuntimeException('Invalid source image.');
        }

        $fit = $this->scaleContain($src, $width, $height);
        $fitW = imagesx($fit);
        $fitH = imagesy($fit);
        $ox = match ($alignX) {
            'left' => 0,
            'right' => max(0, $width - $fitW),
            default => (int) round(($width - $fitW) / 2),
        };
        $oy = match ($alignY) {
            'top' => 0,
            'bottom' => max(0, $height - $fitH),
            default => (int) round(($height - $fitH) / 2),
        };

        $edgeColors = $this->sampleEdgeColors($src);
        // blur slider → how strongly outer edge fades to lighter (0.25…0.75).
        $lighten = 0.25 + ($blur / 40) * 0.50;

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($fit);
            imagedestroy($src);
            throw new \RuntimeException('Cannot create canvas.');
        }

        $this->fillEdgeGradients($canvas, $width, $height, $ox, $oy, $fitW, $fitH, $edgeColors, $lighten);

        imagecopy($canvas, $fit, $ox, $oy, 0, 0, $fitW, $fitH);
        imagedestroy($fit);
        imagedestroy($src);

        $dir = \dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Cannot create output directory.');
        }

        $ok = imagejpeg($canvas, $destPath, $quality);
        imagedestroy($canvas);
        if (!$ok) {
            throw new \RuntimeException('Failed to write result image.');
        }
    }

    /**
     * @return array{top: array{r: int, g: int, b: int}, bottom: array{r: int, g: int, b: int}, left: array{r: int, g: int, b: int}, right: array{r: int, g: int, b: int}, avg: array{r: int, g: int, b: int}}
     */
    private function sampleEdgeColors(\GdImage $src): array
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $band = max(1, (int) round(min($w, $h) * 0.04));

        $top = $this->averageRegion($src, 0, 0, $w, $band);
        $bottom = $this->averageRegion($src, 0, max(0, $h - $band), $w, $band);
        $left = $this->averageRegion($src, 0, 0, $band, $h);
        $right = $this->averageRegion($src, max(0, $w - $band), 0, $band, $h);
        $avg = [
            'r' => (int) round(($top['r'] + $bottom['r'] + $left['r'] + $right['r']) / 4),
            'g' => (int) round(($top['g'] + $bottom['g'] + $left['g'] + $right['g']) / 4),
            'b' => (int) round(($top['b'] + $bottom['b'] + $left['b'] + $right['b']) / 4),
        ];

        return [
            'top' => $top,
            'bottom' => $bottom,
            'left' => $left,
            'right' => $right,
            'avg' => $avg,
        ];
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private function averageRegion(\GdImage $src, int $x, int $y, int $rw, int $rh): array
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $x2 = min($w, $x + max(1, $rw));
        $y2 = min($h, $y + max(1, $rh));
        $x = max(0, $x);
        $y = max(0, $y);

        $sumR = 0;
        $sumG = 0;
        $sumB = 0;
        $n = 0;
        // Subsample for speed on large images.
        $stepX = max(1, (int) floor(($x2 - $x) / 40));
        $stepY = max(1, (int) floor(($y2 - $y) / 40));
        for ($py = $y; $py < $y2; $py += $stepY) {
            for ($px = $x; $px < $x2; $px += $stepX) {
                $rgb = imagecolorat($src, $px, $py);
                $sumR += ($rgb >> 16) & 0xFF;
                $sumG += ($rgb >> 8) & 0xFF;
                $sumB += $rgb & 0xFF;
                ++$n;
            }
        }
        if ($n === 0) {
            return ['r' => 128, 'g' => 128, 'b' => 128];
        }

        return [
            'r' => (int) round($sumR / $n),
            'g' => (int) round($sumG / $n),
            'b' => (int) round($sumB / $n),
        ];
    }

    /**
     * @param array{top: array{r: int, g: int, b: int}, bottom: array{r: int, g: int, b: int}, left: array{r: int, g: int, b: int}, right: array{r: int, g: int, b: int}, avg: array{r: int, g: int, b: int}} $edgeColors
     */
    private function fillEdgeGradients(
        \GdImage $canvas,
        int $width,
        int $height,
        int $ox,
        int $oy,
        int $fitW,
        int $fitH,
        array $edgeColors,
        float $lighten,
    ): void {
        $base = $this->lightenColor($edgeColors['avg'], $lighten);
        $fill = imagecolorallocate($canvas, $base['r'], $base['g'], $base['b']);
        imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, $fill);

        // Top band: lighter at outer edge → edge colour at image boundary.
        if ($oy > 0) {
            $this->paintVerticalBand(
                $canvas,
                0,
                $width,
                0,
                $oy,
                $this->lightenColor($edgeColors['top'], $lighten),
                $edgeColors['top'],
            );
        }

        // Bottom band: edge colour at image boundary → lighter at outer edge.
        $bottomStart = $oy + $fitH;
        if ($bottomStart < $height) {
            $this->paintVerticalBand(
                $canvas,
                0,
                $width,
                $bottomStart,
                $height,
                $edgeColors['bottom'],
                $this->lightenColor($edgeColors['bottom'], $lighten),
            );
        }

        // Left band (middle strip only — corners already covered by top/bottom).
        if ($ox > 0 && $fitH > 0) {
            $this->paintHorizontalBand(
                $canvas,
                0,
                $ox,
                $oy,
                $oy + $fitH,
                $this->lightenColor($edgeColors['left'], $lighten),
                $edgeColors['left'],
            );
        }

        // Right band.
        $rightStart = $ox + $fitW;
        if ($rightStart < $width && $fitH > 0) {
            $this->paintHorizontalBand(
                $canvas,
                $rightStart,
                $width,
                $oy,
                $oy + $fitH,
                $edgeColors['right'],
                $this->lightenColor($edgeColors['right'], $lighten),
            );
        }
    }

    /**
     * @param array{r: int, g: int, b: int} $from Colour at yStart
     * @param array{r: int, g: int, b: int} $to   Colour at yEnd
     */
    private function paintVerticalBand(
        \GdImage $canvas,
        int $x0,
        int $x1,
        int $yStart,
        int $yEnd,
        array $from,
        array $to,
    ): void {
        $h = max(1, $yEnd - $yStart);
        for ($y = $yStart; $y < $yEnd; ++$y) {
            $t = ($y - $yStart) / $h;
            $c = $this->lerpColor($from, $to, $t);
            $col = imagecolorallocate($canvas, $c['r'], $c['g'], $c['b']);
            imageline($canvas, $x0, $y, $x1 - 1, $y, $col);
        }
    }

    /**
     * @param array{r: int, g: int, b: int} $from Colour at xStart
     * @param array{r: int, g: int, b: int} $to   Colour at xEnd
     */
    private function paintHorizontalBand(
        \GdImage $canvas,
        int $xStart,
        int $xEnd,
        int $y0,
        int $y1,
        array $from,
        array $to,
    ): void {
        $w = max(1, $xEnd - $xStart);
        for ($x = $xStart; $x < $xEnd; ++$x) {
            $t = ($x - $xStart) / $w;
            $c = $this->lerpColor($from, $to, $t);
            $col = imagecolorallocate($canvas, $c['r'], $c['g'], $c['b']);
            imageline($canvas, $x, $y0, $x, $y1 - 1, $col);
        }
    }

    /**
     * @param array{r: int, g: int, b: int} $a
     * @param array{r: int, g: int, b: int} $b
     * @return array{r: int, g: int, b: int}
     */
    private function lerpColor(array $a, array $b, float $t): array
    {
        $t = max(0.0, min(1.0, $t));

        return [
            'r' => (int) round($a['r'] + ($b['r'] - $a['r']) * $t),
            'g' => (int) round($a['g'] + ($b['g'] - $a['g']) * $t),
            'b' => (int) round($a['b'] + ($b['b'] - $a['b']) * $t),
        ];
    }

    /**
     * @param array{r: int, g: int, b: int} $c
     * @return array{r: int, g: int, b: int}
     */
    private function lightenColor(array $c, float $amount): array
    {
        $amount = max(0.0, min(1.0, $amount));

        return [
            'r' => (int) round($c['r'] + (255 - $c['r']) * $amount),
            'g' => (int) round($c['g'] + (255 - $c['g']) * $amount),
            'b' => (int) round($c['b'] + (255 - $c['b']) * $amount),
        ];
    }

    /**
     * @param list<string> $allowed
     */
    private function normalizeAlign(string $value, array $allowed, string $default): string
    {
        $value = strtolower(trim($value));

        return \in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Rotate clockwise by 90 / 180 / 270 degrees.
     *
     * @param \GdImage $src
     * @return \GdImage
     */
    private function rotateImage(\GdImage $src, int $degreesClockwise): \GdImage
    {
        $bg = imagecolorallocatealpha($src, 0, 0, 0, 127);
        $rotated = imagerotate($src, 360 - $degreesClockwise, $bg);
        if ($rotated === false) {
            throw new \RuntimeException('Failed to rotate image.');
        }
        imagealphablending($rotated, true);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /** @return \GdImage */
    private function loadImage(string $path): \GdImage
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Source file not found.');
        }

        $info = @getimagesize($path);
        if ($info === false || !isset($info[2])) {
            throw new \RuntimeException('Unsupported or corrupt image.');
        }

        $img = match ($info[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => @imagecreatefrompng($path),
            \IMAGETYPE_WEBP => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            \IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        if ($img === false) {
            throw new \RuntimeException('Cannot decode image.');
        }

        imagealphablending($img, true);
        imagesavealpha($img, true);

        return $img;
    }

    /**
     * @param \GdImage $src
     * @return \GdImage
     */
    private function scaleContain(\GdImage $src, int $maxW, int $maxH): \GdImage
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min($maxW / $sw, $maxH / $sh);
        $nw = max(1, (int) round($sw * $scale));
        $nh = max(1, (int) round($sh * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        if ($out === false) {
            throw new \RuntimeException('Cannot allocate subject image.');
        }
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $nw, $nh, $transparent);
        imagealphablending($out, true);
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);

        return $out;
    }
}
