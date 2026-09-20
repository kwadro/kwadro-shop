<?php

namespace App\Service\GeoIp;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoIpCityResolver
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UkrainianCityNameNormalizer $cityNameNormalizer,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /** @return array{countryCode: string, countryName: string, city: string}|null */
    public function resolveLocationFromIp(?string $ip): ?array
    {
        if ($ip === null || $ip === '' || $this->isPrivateIp($ip)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', sprintf('https://ipwho.is/%s', rawurlencode($ip)), [
                'timeout' => 4,
            ]);
            $data = $response->toArray(false);

            if (($data['success'] ?? false) !== true) {
                return null;
            }

            $countryCode = strtoupper(trim((string) ($data['country_code'] ?? '')));
            $countryName = trim((string) ($data['country'] ?? ''));
            $city = trim((string) ($data['city'] ?? ''));
            if ($countryCode === '' && $city === '') {
                return null;
            }

            if ($city !== '') {
                $city = $this->cityNameNormalizer->normalize($city, $countryCode !== '' ? $countryCode : null);
            }

            return [
                'countryCode' => $countryCode,
                'countryName' => $countryName,
                'city' => $city,
            ];
        } catch (\Throwable $exception) {
            $this->logger?->warning('GeoIP city lookup failed.', [
                'ip' => $ip,
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
