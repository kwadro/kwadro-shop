<?php

namespace App\PartnerProgram\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Captures Facebook / Google Ads click identifiers from a partner landing request
 * (same signals fbevents.js and Google Ads tags use client-side).
 */
final class AdClickAttribution
{
    public const SOURCE_FACEBOOK = 'facebook';
    public const SOURCE_GOOGLE_ADS = 'google_ads';

    public const COOKIE_FBC = '_fbc';
    public const COOKIE_FBP = '_fbp';
    public const COOKIE_GCLID = '_gcl_aw';

    /**
     * @return array{
     *     source: ?string,
     *     fbclid: ?string,
     *     fbc: ?string,
     *     fbp: ?string,
     *     gclid: ?string,
     *     gbraid: ?string,
     *     wbraid: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     new_fbc: ?string,
     *     new_fbp: ?string
     * }
     */
    public function extract(Request $request): array
    {
        $fbclid = $this->queryString($request, 'fbclid');
        $gclid = $this->queryString($request, 'gclid');
        $gbraid = $this->queryString($request, 'gbraid');
        $wbraid = $this->queryString($request, 'wbraid');
        $utmSource = $this->queryString($request, 'utm_source');
        $utmMedium = $this->queryString($request, 'utm_medium');
        $utmCampaign = $this->queryString($request, 'utm_campaign');

        $existingFbc = $this->cookieString($request, self::COOKIE_FBC);
        $existingFbp = $this->cookieString($request, self::COOKIE_FBP);

        $newFbc = null;
        $fbc = $existingFbc;
        if ($fbclid !== null) {
            // Meta Pixel format: fb.1.{creationTimeMs}.{fbclid}
            $newFbc = sprintf('fb.1.%d.%s', (int) round(microtime(true) * 1000), $fbclid);
            $fbc = $newFbc;
        }

        $newFbp = null;
        $fbp = $existingFbp;
        if ($fbp === null) {
            // Same shape as fbevents.js _fbp when missing.
            $newFbp = sprintf('fb.1.%d.%d', (int) round(microtime(true) * 1000), random_int(1000000000, 9999999999));
            $fbp = $newFbp;
        }

        $source = $this->resolveSource($fbclid, $gclid, $gbraid, $wbraid, $utmSource, $utmMedium);

        return [
            'source' => $source,
            'fbclid' => $fbclid,
            'fbc' => $fbc,
            'fbp' => $fbp,
            'gclid' => $gclid,
            'gbraid' => $gbraid,
            'wbraid' => $wbraid,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'new_fbc' => $newFbc,
            'new_fbp' => $newFbp,
        ];
    }

    /**
     * Payload fields for affiliate POST /api/views (no cookie-only helpers).
     *
     * @param array{
     *     source: ?string,
     *     fbclid: ?string,
     *     fbc: ?string,
     *     fbp: ?string,
     *     gclid: ?string,
     *     gbraid: ?string,
     *     wbraid: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     new_fbc?: ?string,
     *     new_fbp?: ?string
     * } $attribution
     *
     * @return array<string, string>
     */
    public function toApiPayload(array $attribution): array
    {
        $payload = [];
        foreach ([
            'source',
            'fbclid',
            'fbc',
            'fbp',
            'gclid',
            'gbraid',
            'wbraid',
            'utm_source',
            'utm_medium',
            'utm_campaign',
        ] as $key) {
            $value = $attribution[$key] ?? null;
            if (\is_string($value) && $value !== '') {
                $payload[$key] = mb_substr($value, 0, 512);
            }
        }

        return $payload;
    }

    private function resolveSource(
        ?string $fbclid,
        ?string $gclid,
        ?string $gbraid,
        ?string $wbraid,
        ?string $utmSource,
        ?string $utmMedium,
    ): ?string {
        if ($fbclid !== null) {
            return self::SOURCE_FACEBOOK;
        }
        if ($gclid !== null || $gbraid !== null || $wbraid !== null) {
            return self::SOURCE_GOOGLE_ADS;
        }

        $source = mb_strtolower((string) $utmSource);
        $medium = mb_strtolower((string) $utmMedium);

        if (
            str_contains($source, 'facebook')
            || str_contains($source, 'fb')
            || str_contains($source, 'instagram')
            || str_contains($source, 'meta')
            || $source === 'ig'
        ) {
            return self::SOURCE_FACEBOOK;
        }

        if (
            str_contains($source, 'google')
            || $medium === 'cpc'
            || $medium === 'ppc'
            || str_contains($source, 'adwords')
        ) {
            return self::SOURCE_GOOGLE_ADS;
        }

        return null;
    }

    private function queryString(Request $request, string $name): ?string
    {
        $raw = $request->query->get($name);
        if (!\is_string($raw)) {
            return null;
        }
        $raw = trim($raw);

        return $raw !== '' ? $raw : null;
    }

    private function cookieString(Request $request, string $name): ?string
    {
        $raw = $request->cookies->get($name);
        if (!\is_string($raw)) {
            return null;
        }
        $raw = trim($raw);

        return $raw !== '' ? $raw : null;
    }
}
