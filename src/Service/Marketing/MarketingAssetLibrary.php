<?php

namespace App\Service\Marketing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Filesystem library for reusable marketing images and shapes.
 *
 * @phpstan-type LibraryItem array{
 *     filename: string,
 *     url: string,
 *     name: string,
 *     savedAt: string|null,
 *     mime: string|null,
 *     size: int|null
 * }
 */
final class MarketingAssetLibrary
{
    public const KIND_MEDIA = 'media';
    public const KIND_SHAPE = 'shapes';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getDir(string $kind): string
    {
        $kind = $this->normalizeKind($kind);

        return $this->projectDir.'/public/uploads/marketing/'.$kind;
    }

    public function getPublicUrl(string $kind, string $filename): string
    {
        $kind = $this->normalizeKind($kind);

        return '/uploads/marketing/'.$kind.'/'.$filename;
    }

    public function ensureDir(string $kind): void
    {
        $dir = $this->getDir($kind);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create library directory.');
        }
    }

    /**
     * @return list<LibraryItem>
     */
    public function list(string $kind, int $limit = 200): array
    {
        $dir = $this->getDir($kind);
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/*.{jpg,jpeg,png,webp,gif,svg}', \GLOB_BRACE) ?: [];
        rsort($files, \SORT_STRING);
        $items = [];
        foreach (\array_slice($files, 0, max(1, $limit)) as $path) {
            $filename = basename($path);
            if (!$this->isSafeFilename($filename)) {
                continue;
            }
            $items[] = $this->itemFromPath($kind, $path);
        }

        return $items;
    }

    /**
     * @return LibraryItem|null
     */
    public function get(string $kind, string $filename): ?array
    {
        if (!$this->isSafeFilename($filename)) {
            return null;
        }
        $path = $this->resolvePath($kind, $filename);
        if ($path === null) {
            return null;
        }

        return $this->itemFromPath($kind, $path);
    }

    /**
     * @return LibraryItem
     */
    public function addFromUpload(string $kind, \Symfony\Component\HttpFoundation\File\UploadedFile $file, ?string $name = null): array
    {
        $mime = (string) $file->getMimeType();
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
        ];
        if (!\in_array($mime, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported image type.');
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };

        $this->ensureDir($kind);
        $prefix = $this->normalizeKind($kind) === self::KIND_SHAPE ? 'shape' : 'media';
        $filename = $prefix.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.'.$ext;
        $file->move($this->getDir($kind), $filename);

        $label = trim((string) $name);
        if ($label === '') {
            $label = pathinfo((string) $file->getClientOriginalName(), \PATHINFO_FILENAME) ?: $filename;
        }
        $this->writeMeta($kind, $filename, [
            'name' => $label,
            'savedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'originalName' => (string) $file->getClientOriginalName(),
        ]);

        $item = $this->get($kind, $filename);
        if ($item === null) {
            throw new \RuntimeException('Failed to store library item.');
        }

        return $item;
    }

    /**
     * Copy an existing public file into the library (e.g. after transform/upload in editor).
     *
     * @return LibraryItem
     */
    public function addFromPath(string $kind, string $sourcePath, ?string $name = null, ?string $preferredExt = null): array
    {
        if (!is_file($sourcePath)) {
            throw new \InvalidArgumentException('Source file not found.');
        }

        $info = @getimagesize($sourcePath);
        $ext = $preferredExt;
        if ($ext === null || $ext === '') {
            $ext = match ($info[2] ?? 0) {
                \IMAGETYPE_PNG => 'png',
                \IMAGETYPE_WEBP => 'webp',
                \IMAGETYPE_GIF => 'gif',
                default => 'jpg',
            };
        }

        $this->ensureDir($kind);
        $prefix = $this->normalizeKind($kind) === self::KIND_SHAPE ? 'shape' : 'media';
        $filename = $prefix.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.'.$ext;
        $dest = $this->getDir($kind).'/'.$filename;
        if (!@copy($sourcePath, $dest)) {
            throw new \RuntimeException('Failed to copy into library.');
        }

        $label = trim((string) $name);
        if ($label === '') {
            $label = pathinfo(basename($sourcePath), \PATHINFO_FILENAME) ?: $filename;
        }
        $this->writeMeta($kind, $filename, [
            'name' => $label,
            'savedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'originalName' => basename($sourcePath),
        ]);

        $item = $this->get($kind, $filename);
        if ($item === null) {
            throw new \RuntimeException('Failed to store library item.');
        }

        return $item;
    }

    public function rename(string $kind, string $filename, string $name): bool
    {
        if ($this->get($kind, $filename) === null) {
            return false;
        }
        $meta = $this->readMeta($kind, $filename) ?? [];
        $meta['name'] = trim($name) !== '' ? trim($name) : ($meta['name'] ?? $filename);
        $meta['savedAt'] = $meta['savedAt'] ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->writeMeta($kind, $filename, $meta);

        return true;
    }

    public function delete(string $kind, string $filename): bool
    {
        $path = $this->resolvePath($kind, $filename);
        if ($path === null) {
            return false;
        }
        @unlink($path);
        $meta = $path.'.json';
        if (is_file($meta)) {
            @unlink($meta);
        }

        return true;
    }

    public function isSafeFilename(string $filename): bool
    {
        return (bool) preg_match('#^(media|shape|bg|overlay|img|tf)-[A-Za-z0-9._-]+\.(jpe?g|png|webp|gif|svg)$#i', $filename);
    }

    private function normalizeKind(string $kind): string
    {
        return $kind === self::KIND_SHAPE ? self::KIND_SHAPE : self::KIND_MEDIA;
    }

    private function resolvePath(string $kind, string $filename): ?string
    {
        if (!$this->isSafeFilename($filename)) {
            return null;
        }
        $dir = realpath($this->getDir($kind));
        if ($dir === false) {
            return null;
        }
        $path = $dir.'/'.$filename;
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $dir.\DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    /**
     * @return LibraryItem
     */
    private function itemFromPath(string $kind, string $path): array
    {
        $filename = basename($path);
        $meta = $this->readMeta($kind, $filename) ?? [];

        return [
            'filename' => $filename,
            'url' => $this->getPublicUrl($kind, $filename),
            'name' => isset($meta['name']) && \is_string($meta['name']) && $meta['name'] !== ''
                ? $meta['name']
                : pathinfo($filename, \PATHINFO_FILENAME),
            'savedAt' => isset($meta['savedAt']) && \is_string($meta['savedAt']) ? $meta['savedAt'] : null,
            'mime' => @mime_content_type($path) ?: null,
            'size' => @filesize($path) ?: null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function readMeta(string $kind, string $filename): ?array
    {
        $path = $this->getDir($kind).'/'.$filename.'.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return \is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $meta */
    private function writeMeta(string $kind, string $filename, array $meta): void
    {
        $path = $this->getDir($kind).'/'.$filename.'.json';
        file_put_contents($path, json_encode($meta, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT));
    }
}
