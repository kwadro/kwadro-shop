<?php

namespace App\Service\Request;

use App\Repository\BotUserAgentMarkerRepository;

/**
 * Heuristic bot / crawler detection from User-Agent.
 *
 * Markers are editable in admin (BotUserAgentMarker). Empty UA is treated as
 * "suspicious", not as a confirmed bot.
 */
final class BotUserAgentClassifier
{
    /**
     * Seed / fallback list when the admin table is empty.
     *
     * @var list<string>
     */
    public const DEFAULT_MARKERS = [
        'googlebot',
        'google.com',
        'bingbot',
        'bing.com',
        'bingpreview',
        'yandexbot',
        'yandex.com',
        'duckduckbot',
        'baiduspider',
        'facebookexternalhit',
        'facebot',
        'twitterbot',
        'linkedinbot',
        'slackbot',
        'discordbot',
        'telegrambot',
        'applebot',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
        'dotbot',
        'petalbot',
        'bytespider',
        'gptbot',
        'claudebot',
        'anthropic',
        'chatgpt',
        'crawler',
        'spider',
        'slurp',
        'bot/',
        'bot;',
        'bot)',
        'crawl',
    ];

    public function __construct(
        private readonly BotUserAgentMarkerRepository $markerRepository,
    ) {
    }

    public function isEmpty(?string $userAgent): bool
    {
        return $userAgent === null || trim($userAgent) === '';
    }

    public function isLikelyBot(?string $userAgent): bool
    {
        if ($this->isEmpty($userAgent)) {
            return false;
        }

        $ua = mb_strtolower(trim((string) $userAgent));
        foreach ($this->markers() as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    public function isSuspicious(?string $userAgent): bool
    {
        return $this->isEmpty($userAgent);
    }

    /**
     * Active markers from admin, or {@see DEFAULT_MARKERS} if none configured.
     *
     * @return list<string>
     */
    public function markers(): array
    {
        $fromDb = $this->markerRepository->findActiveMarkers();

        return $fromDb !== [] ? $fromDb : self::DEFAULT_MARKERS;
    }

    /**
     * SQL LIKE patterns for aggregate reports.
     *
     * @return list<string>
     */
    public function sqlLikePatterns(): array
    {
        $patterns = [];
        foreach ($this->markers() as $marker) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $marker);
            $patterns[] = '%'.$escaped.'%';
        }

        return $patterns;
    }
}
