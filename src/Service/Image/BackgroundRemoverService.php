<?php

namespace App\Service\Image;

/**
 * Removes a light/white background and cleans light fringe around the subject.
 * Produces a PNG with a binary (hard) alpha channel.
 *
 * Modes:
 * - full: remove every light/white pixel matching the background criteria
 * - contour: remove only background connected to the image border (keep interior)
 */
final class BackgroundRemoverService
{
    public const MAX_SIDE = 2400;
    public const MODE_FULL = 'full';
    public const MODE_CONTOUR = 'contour';

    /**
     * @param array{erode?: int, whiteThreshold?: int, softThreshold?: int, edgeDarken?: int, mode?: string} $options
     */
    public function removeFromGdImage(\GdImage $src, array $options = []): \GdImage
    {
        $erode = max(0, min(4, (int) ($options['erode'] ?? 1)));
        $whiteThreshold = max(180, min(250, (int) ($options['whiteThreshold'] ?? 215)));
        $softThreshold = max(120, min($whiteThreshold - 1, (int) ($options['softThreshold'] ?? 200)));
        $edgeDarken = max(40, min(90, (int) ($options['edgeDarken'] ?? 55)));
        $mode = ($options['mode'] ?? self::MODE_FULL) === self::MODE_CONTOUR
            ? self::MODE_CONTOUR
            : self::MODE_FULL;

        $src = $this->ensureTrueColor($src);
        $w = imagesx($src);
        $h = imagesy($src);
        $n = $w * $h;

        // Compact masks: 1 byte per pixel (0/1) — far cheaper than PHP arrays of ints.
        $fg = str_repeat("\0", $n);
        $bgCandidate = str_repeat("\0", $n);
        for ($y = 0; $y < $h; ++$y) {
            $row = $y * $w;
            for ($x = 0; $x < $w; ++$x) {
                $c = imagecolorat($src, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $br = ($r + $g + $b) / 3.0;
                $sat = max($r, $g, $b) - min($r, $g, $b);
                $isBg = ($br >= $whiteThreshold && $sat <= 25)
                    || ($br >= 240 && $sat <= 20)
                    || ($br >= $softThreshold && $sat <= 18 && $br >= 220);
                if ($isBg) {
                    $bgCandidate[$row + $x] = "\1";
                } else {
                    $fg[$row + $x] = "\1";
                }
            }
        }

        if ($mode === self::MODE_CONTOUR) {
            // Only clear background that touches the frame edge; keep holes inside the subject.
            $exteriorBg = $this->floodExteriorBackground($bgCandidate, $w, $h);
            unset($bgCandidate, $fg);
            $keep = str_repeat("\0", $n);
            for ($i = 0; $i < $n; ++$i) {
                if ($exteriorBg[$i] !== "\1") {
                    $keep[$i] = "\1";
                }
            }
            unset($exteriorBg);
        } else {
            unset($bgCandidate);
            // Keep meaningful shapes (icons/frames); drop tiny speckles only.
            $keep = $this->keepLargeComponents($fg, $w, $h, 6);
            unset($fg);
        }

        // Never morphologically shrink solid strokes — that deletes thin frames.
        // "Erode" only peels light/white fringe pixels from the silhouette.
        for ($i = 0; $i < $erode; ++$i) {
            $keep = $this->erodeLightFringe($keep, $src, $w, $h);
        }

        $out = imagecreatetruecolor($w, $h);
        if ($out === false) {
            throw new \RuntimeException('Cannot create output image.');
        }
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $clear = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $w, $h, $clear);

        for ($y = 0; $y < $h; ++$y) {
            $row = $y * $w;
            for ($x = 0; $x < $w; ++$x) {
                $i = $row + $x;
                if ($keep[$i] !== "\1") {
                    continue;
                }

                $c = imagecolorat($src, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $br = ($r + $g + $b) / 3.0;
                $sat = max($r, $g, $b) - min($r, $g, $b);

                // Drop residual light fringe on the edge; keep colored line art as-is.
                if ($this->isMaskEdge($keep, $w, $h, $x, $y) && $br >= 170 && $sat < 35) {
                    continue;
                }

                if ($this->isMaskEdge($keep, $w, $h, $x, $y) && $sat < 30 && $br > $edgeDarken) {
                    [$r, $g, $b] = $this->darkestNeighbor($src, $keep, $w, $h, $x, $y, $r, $g, $b);
                    $br2 = ($r + $g + $b) / 3.0;
                    $sat2 = max($r, $g, $b) - min($r, $g, $b);
                    if ($sat2 < 30 && $br2 > $edgeDarken) {
                        $scale = $edgeDarken / max(1.0, $br2);
                        $r = max(0, min(255, (int) round($r * $scale)));
                        $g = max(0, min(255, (int) round($g * $scale)));
                        $b = max(0, min(255, (int) round($b * $scale)));
                    }
                }

                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, 0));
            }
        }

        return $out;
    }

    /**
     * @param array{erode?: int, whiteThreshold?: int, softThreshold?: int, edgeDarken?: int, maxSide?: int, mode?: string} $options
     */
    public function removeFromPath(string $inputPath, string $outputPath, array $options = []): void
    {
        $previous = ini_get('memory_limit');
        if ($this->memoryLimitBytes($previous) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }

        try {
            $src = $this->loadImage($inputPath);
            $maxSide = max(400, min(4000, (int) ($options['maxSide'] ?? self::MAX_SIDE)));
            $src = $this->fitMaxSide($src, $maxSide);
            $out = $this->removeFromGdImage($src, $options);
            imagedestroy($src);

            $dir = \dirname($outputPath);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                imagedestroy($out);
                throw new \RuntimeException('Cannot create output directory.');
            }

            if (!imagepng($out, $outputPath, 6)) {
                imagedestroy($out);
                throw new \RuntimeException('Cannot write PNG output.');
            }
            imagedestroy($out);
        } finally {
            if (\is_string($previous) && $previous !== '') {
                @ini_set('memory_limit', $previous);
            }
        }
    }

    public function loadImage(string $path): \GdImage
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException('Input file not found.');
        }

        $info = @getimagesize($path);
        if ($info === false) {
            throw new \InvalidArgumentException('Unsupported or corrupt image.');
        }

        $mime = $info['mime'] ?? '';
        $img = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };

        if ($img === false) {
            throw new \InvalidArgumentException('Cannot decode image.');
        }

        return $this->ensureTrueColor($img);
    }

    /**
     * Resize keeping aspect ratio to the given height. Returns a new image.
     */
    public function resizeToHeight(\GdImage $src, int $targetHeight): \GdImage
    {
        $src = $this->ensureTrueColor($src);
        $w = imagesx($src);
        $h = imagesy($src);
        $targetHeight = max(16, min(4000, $targetHeight));
        if ($h === $targetHeight) {
            return $src;
        }

        $scale = $targetHeight / max(1, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = $targetHeight;
        $resized = imagecreatetruecolor($nw, $nh);
        if ($resized === false) {
            throw new \RuntimeException('Cannot resize image.');
        }
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $clear = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $nw, $nh, $clear);
        imagealphablending($resized, true);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagedestroy($src);

        return $resized;
    }

    private function ensureTrueColor(\GdImage $img): \GdImage
    {
        if (imageistruecolor($img)) {
            imagealphablending($img, false);
            imagesavealpha($img, true);

            return $img;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $tc = imagecreatetruecolor($w, $h);
        if ($tc === false) {
            throw new \RuntimeException('Cannot convert image to truecolor.');
        }
        imagealphablending($tc, false);
        imagesavealpha($tc, true);
        $clear = imagecolorallocatealpha($tc, 0, 0, 0, 127);
        imagefilledrectangle($tc, 0, 0, $w, $h, $clear);
        imagecopy($tc, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);

        return $tc;
    }

    private function fitMaxSide(\GdImage $img, int $maxSide): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $side = max($w, $h);
        if ($side <= $maxSide) {
            return $img;
        }

        $scale = $maxSide / $side;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $resized = imagecreatetruecolor($nw, $nh);
        if ($resized === false) {
            throw new \RuntimeException('Cannot resize image.');
        }
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $clear = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $nw, $nh, $clear);
        imagealphablending($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagedestroy($img);

        return $resized;
    }

    /**
     * Flood-fill light/white candidates starting from the image border.
     * Result marks only exterior background (connected to the frame edge).
     */
    private function floodExteriorBackground(string $bgCandidate, int $w, int $h): string
    {
        $n = $w * $h;
        $exterior = str_repeat("\0", $n);
        $queue = [];

        for ($x = 0; $x < $w; ++$x) {
            $top = $x;
            if ($bgCandidate[$top] === "\1" && $exterior[$top] !== "\1") {
                $exterior[$top] = "\1";
                $queue[] = $top;
            }
            if ($h > 1) {
                $bottom = ($h - 1) * $w + $x;
                if ($bgCandidate[$bottom] === "\1" && $exterior[$bottom] !== "\1") {
                    $exterior[$bottom] = "\1";
                    $queue[] = $bottom;
                }
            }
        }
        for ($y = 1; $y < $h - 1; ++$y) {
            $left = $y * $w;
            if ($bgCandidate[$left] === "\1" && $exterior[$left] !== "\1") {
                $exterior[$left] = "\1";
                $queue[] = $left;
            }
            if ($w > 1) {
                $right = $left + ($w - 1);
                if ($bgCandidate[$right] === "\1" && $exterior[$right] !== "\1") {
                    $exterior[$right] = "\1";
                    $queue[] = $right;
                }
            }
        }

        $qi = 0;
        $neighbors = [1, -1, $w, -$w, $w + 1, $w - 1, -$w + 1, -$w - 1];
        while ($qi < \count($queue)) {
            $i = $queue[$qi++];
            $x = $i % $w;
            $y = intdiv($i, $w);
            foreach ($neighbors as $delta) {
                $ni = $i + $delta;
                if ($ni < 0 || $ni >= $n) {
                    continue;
                }
                $nx = $ni % $w;
                $ny = intdiv($ni, $w);
                if (abs($nx - $x) > 1 || abs($ny - $y) > 1) {
                    continue;
                }
                if ($bgCandidate[$ni] !== "\1" || $exterior[$ni] === "\1") {
                    continue;
                }
                $exterior[$ni] = "\1";
                $queue[] = $ni;
            }
        }

        return $exterior;
    }

    /**
     * Remove only light/desaturated fringe pixels on the mask edge.
     * Preserves thin colored strokes (frames, icons, arrows).
     */
    private function erodeLightFringe(string $mask, \GdImage $src, int $w, int $h): string
    {
        $next = $mask;
        for ($y = 0; $y < $h; ++$y) {
            for ($x = 0; $x < $w; ++$x) {
                $i = $y * $w + $x;
                if ($mask[$i] !== "\1" || !$this->isMaskEdge($mask, $w, $h, $x, $y)) {
                    continue;
                }
                $c = imagecolorat($src, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $br = ($r + $g + $b) / 3.0;
                $sat = max($r, $g, $b) - min($r, $g, $b);
                if ($br >= 165 && $sat < 40) {
                    $next[$i] = "\0";
                }
            }
        }

        return $next;
    }

    /**
     * Keep connected foreground components that have at least $minSize pixels.
     */
    private function keepLargeComponents(string $fg, int $w, int $h, int $minSize): string
    {
        $n = $w * $h;
        $keep = str_repeat("\0", $n);
        $seen = str_repeat("\0", $n);
        $neighbors = [1, -1, $w, -$w, $w + 1, $w - 1, -$w + 1, -$w - 1];

        for ($start = 0; $start < $n; ++$start) {
            if ($fg[$start] !== "\1" || $seen[$start] === "\1") {
                continue;
            }

            $queue = [$start];
            $seen[$start] = "\1";
            $qi = 0;
            $component = [];

            while ($qi < \count($queue)) {
                $i = $queue[$qi++];
                $component[] = $i;
                $x = $i % $w;
                $y = intdiv($i, $w);
                foreach ($neighbors as $delta) {
                    $ni = $i + $delta;
                    if ($ni < 0 || $ni >= $n) {
                        continue;
                    }
                    $nx = $ni % $w;
                    $ny = intdiv($ni, $w);
                    if (abs($nx - $x) > 1 || abs($ny - $y) > 1) {
                        continue;
                    }
                    if ($fg[$ni] !== "\1" || $seen[$ni] === "\1") {
                        continue;
                    }
                    $seen[$ni] = "\1";
                    $queue[] = $ni;
                }
            }

            if (\count($component) < $minSize) {
                continue;
            }
            foreach ($component as $i) {
                $keep[$i] = "\1";
            }
        }

        // Fallback: if everything was filtered out, keep the original foreground.
        if (!str_contains($keep, "\1")) {
            return $fg;
        }

        return $keep;
    }

    private function isMaskEdge(string $mask, int $w, int $h, int $x, int $y): bool
    {
        $i = $y * $w + $x;
        if ($x === 0 || $y === 0 || $x === $w - 1 || $y === $h - 1) {
            return true;
        }

        return $mask[$i - 1] !== "\1"
            || $mask[$i + 1] !== "\1"
            || $mask[$i - $w] !== "\1"
            || $mask[$i + $w] !== "\1";
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function darkestNeighbor(\GdImage $src, string $mask, int $w, int $h, int $x, int $y, int $r, int $g, int $b): array
    {
        $best = ($r + $g + $b) / 3.0;
        $rr = $r;
        $gg = $g;
        $bb = $b;
        for ($dy = -3; $dy <= 3; ++$dy) {
            for ($dx = -3; $dx <= 3; ++$dx) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                    continue;
                }
                if ($mask[$ny * $w + $nx] !== "\1") {
                    continue;
                }
                $c2 = imagecolorat($src, $nx, $ny);
                $r2 = ($c2 >> 16) & 0xFF;
                $g2 = ($c2 >> 8) & 0xFF;
                $b2 = $c2 & 0xFF;
                $br2 = ($r2 + $g2 + $b2) / 3.0;
                if ($br2 < $best) {
                    $best = $br2;
                    $rr = $r2;
                    $gg = $g2;
                    $bb = $b2;
                }
            }
        }

        return [$rr, $gg, $bb];
    }

    private function memoryLimitBytes(mixed $value): int
    {
        if (!\is_string($value) || $value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($value, -1));
        $num = (float) $value;
        return (int) match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (float) $value,
        };
    }
}
