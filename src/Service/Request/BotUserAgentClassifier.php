<?php

namespace App\Service\Request;

/**
 * Heuristic bot / crawler detection from User-Agent.
 *
 * Empty UA is treated as "suspicious", not as a confirmed bot (scrapers and
 * privacy tools both omit UA; decide policy separately by IP volume / rate).
 */
final class BotUserAgentClassifier
{
    /** @var list<string> Substrings matched case-insensitively */
    private const BOT_MARKERS = [
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
        foreach (self::BOT_MARKERS as $marker) {
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
     * SQL LIKE patterns for aggregate reports (keep in sync with {@see BOT_MARKERS} loosely).
     *
     * @return list<string>
     */
    public static function sqlLikePatterns(): array
    {
        return [
            '%googlebot%',
            '%google.com%',
            '%bingbot%',
            '%bing.com%',
            '%yandex%',
            '%baiduspider%',
            '%duckduckbot%',
            '%facebookexternalhit%',
            '%twitterbot%',
            '%linkedinbot%',
            '%semrushbot%',
            '%ahrefsbot%',
            '%gptbot%',
            '%bytespider%',
            '%crawler%',
            '%spider%',
            '%bot/%',
        ];
    }
}
