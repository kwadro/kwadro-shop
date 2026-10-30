<?php

namespace App\AiContentAgent\Service;

final class TopicKey
{
    public static function fromTitle(string $title): string
    {
        $normalized = mb_strtolower(trim($title));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return hash('sha256', $normalized);
    }

    public static function fromUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || !isset($parts['host'])) {
            return hash('sha256', mb_strtolower(trim($url)));
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return hash('sha256', $scheme.'://'.$host.$port.$path.$query);
    }
}
