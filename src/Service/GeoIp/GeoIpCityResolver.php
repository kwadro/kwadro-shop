<?php

namespace App\Service\GeoIp;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoIpCityResolver
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UkrainianCityNameNormalizer $cityNameNormalizer,
        private readonly GeoIpFileLogger $geoIpFileLogger,
    ) {
    }

    /** @return array{countryCode: string, countryName: string, city: string}|null */
    public function resolveLocationFromIp(?string $ip): ?array
    {
        if ($ip === null || $ip === '') {
            $this->geoIpFileLogger->log('lookup_skipped', [
                'ip' => $ip,
                'reason' => 'empty_ip',
            ]);

            return null;
        }

        if ($this->isPrivateIp($ip)) {
            $this->geoIpFileLogger->log('lookup_skipped', [
                'ip' => $ip,
                'reason' => 'private_or_reserved_ip',
            ]);

            return null;
        }

        try {
            $response = $this->httpClient->request('GET', sprintf('https://ipwho.is/%s', rawurlencode($ip)), [
                'timeout' => 4,
            ]);
            $data = $response->toArray(false);

            if (($data['success'] ?? false) !== true) {
                $this->geoIpFileLogger->log('lookup_failed', [
                    'ip' => $ip,
                    'provider' => 'ipwho.is',
                    'message' => (string) ($data['message'] ?? 'GeoIP provider returned success=false'),
                ]);

                return null;
            }

            $countryCode = strtoupper(trim((string) ($data['country_code'] ?? '')));
            $countryName = trim((string) ($data['country'] ?? ''));
            $rawCity = trim((string) ($data['city'] ?? ''));
            $city = $rawCity;
            if ($countryCode === '' && $city === '') {
                $this->geoIpFileLogger->log('lookup_failed', [
                    'ip' => $ip,
                    'provider' => 'ipwho.is',
                    'message' => 'Empty country and city in provider response',
                ]);

                return null;
            }

            if ($city !== '') {
                $city = $this->cityNameNormalizer->normalize($city, $countryCode !== '' ? $countryCode : null);
            }

            $location = [
                'countryCode' => $countryCode,
                'countryName' => $countryName,
                'city' => $city,
            ];

            $this->geoIpFileLogger->log('lookup_success', [
                'ip' => $ip,
                'provider' => 'ipwho.is',
                'countryCode' => $countryCode,
                'countryName' => $countryName,
                'cityRaw' => $rawCity,
                'cityNormalized' => $city,
                'isUkraine' => $countryCode === 'UA',
                'region' => trim((string) ($data['region'] ?? '')),
            ]);

            return $location;
        } catch (\Throwable $exception) {
            $this->geoIpFileLogger->log('lookup_error', [
                'ip' => $ip,
                'provider' => 'ipwho.is',
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function resolveCityFromIp(?string $ip): ?string
    {
        $location = $this->resolveLocationFromIp($ip);

        return $location !== null && $location['city'] !== '' ? $location['city'] : null;
    }

    private function isPrivateIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, 'fe80:')) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
