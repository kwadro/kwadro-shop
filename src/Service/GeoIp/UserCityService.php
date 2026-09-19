<?php

namespace App\Service\GeoIp;

use App\Service\NovaPoshtaClient;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UserCityService
{
    public const COOKIE_NAME = 'user_city';
    public const COOKIE_DATA_NAME = 'user_city_data';
    public const SESSION_KEY = 'shop_user_city';
    public const REQUEST_DATA_ATTRIBUTE = '_user_city_data';
    public const ATTACH_COOKIE_ATTRIBUTE = '_user_city_attach';
    private const COOKIE_TTL = 31536000;

    /** @var list<string> */
    public const POPULAR_CITIES = [
        'Київ',
        'Харків',
        'Одеса',
        'Дніпро',
        'Івано-Франківськ',
        'Львів',
    ];

    public function __construct(
        private readonly GeoIpCityResolver $geoIpCityResolver,
        private readonly NovaPoshtaClient $novaPoshtaClient,
        private readonly RequestStack $requestStack,
        private readonly string $defaultCity = 'Івано-Франківськ',
    ) {
    }

    /** @return array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} */
    public function resolveData(?Request $request = null): array
    {
        $request ??= $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->defaultCityData();
        }

        if ($request->attributes->has(self::REQUEST_DATA_ATTRIBUTE)) {
            $data = $request->attributes->get(self::REQUEST_DATA_ATTRIBUTE);
            if (\is_array($data)) {
                return $this->normalizeCityData($data) ?? $this->defaultCityData();
            }
        }

        $stored = $this->readStoredData($request);
        if ($stored !== null) {
            $request->attributes->set(self::REQUEST_DATA_ATTRIBUTE, $stored);

            return $stored;
        }

        $detectedName = $this->geoIpCityResolver->resolveCityFromIp($request->getClientIp());
        $data = $this->buildCityData($detectedName ?? $this->defaultCity);
        $this->stageCityData($request, $data);

        return $data;
    }

    public function resolve(?Request $request = null): string
    {
        return $this->resolveData($request)['name'];
    }

    /** @param array{name?: string, ref?: string, subtitle?: string, label?: string, settlementRef?: string, warehouseCityRef?: string, hasLocalWarehouses?: bool} $data */
    public function rememberCityData(Request $request, array $data): void
    {
        $normalized = $this->normalizeCityData($data);
        if ($normalized === null) {
            return;
        }

        if ($normalized['ref'] === '' && $this->novaPoshtaClient->isConfigured()) {
            $matched = $this->novaPoshtaClient->findCityByName($normalized['name']);
            if ($matched !== null) {
                $normalized = $this->normalizeCityData($matched) ?? $normalized;
            }
        }

        $this->stageCityData($request, $normalized);

        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, $normalized);
        }
    }

    public function rememberCity(Request $request, string $city): void
    {
        $this->rememberCityData($request, ['name' => $city]);
    }

    public function getDefaultCity(): string
    {
        return $this->defaultCity;
    }

    public function isCourierDeliveryAvailable(?Request $request = null): bool
    {
        return $this->isCourierCityName($this->resolveData($request)['name']);
    }

    public function isCourierCityName(string $cityName): bool
    {
        return mb_strtolower($this->normalizeCity($cityName)) === mb_strtolower($this->defaultCity);
    }

    /** @return array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} */
    public function getDefaultCityData(): array
    {
        return $this->defaultCityData();
    }

    public function shouldAttachCookie(Request $request): bool
    {
        return $request->attributes->has(self::ATTACH_COOKIE_ATTRIBUTE);
    }

    /** @return array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} */
    public function getCityDataForCookie(Request $request): array
    {
        $data = $request->attributes->get(self::ATTACH_COOKIE_ATTRIBUTE);

        return \is_array($data) ? ($this->normalizeCityData($data) ?? $this->defaultCityData()) : $this->defaultCityData();
    }

    public function attachCookie(\Symfony\Component\HttpFoundation\Response $response, Request $request, array $cityData): void
    {
        $normalized = $this->normalizeCityData($cityData) ?? $this->defaultCityData();
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if (!\is_string($encoded)) {
            return;
        }

        $response->headers->setCookie(
            Cookie::create(self::COOKIE_DATA_NAME, $encoded)
                ->withExpires(time() + self::COOKIE_TTL)
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        $response->headers->setCookie(
            Cookie::create(self::COOKIE_NAME, $normalized['name'])
                ->withExpires(time() + self::COOKIE_TTL)
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
    }

    /** @return array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool}|null */
    private function readStoredData(Request $request): ?array
    {
        if ($request->hasSession() && $request->getSession()->has(self::SESSION_KEY)) {
            $sessionData = $request->getSession()->get(self::SESSION_KEY);
            if (\is_array($sessionData)) {
                $normalized = $this->normalizeCityData($sessionData);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $cookieJson = (string) $request->cookies->get(self::COOKIE_DATA_NAME, '');
        if ($cookieJson !== '') {
            $decoded = json_decode($cookieJson, true);
            if (\is_array($decoded)) {
                $normalized = $this->normalizeCityData($decoded);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $legacyName = $this->normalizeCity((string) $request->cookies->get(self::COOKIE_NAME, ''));
        if ($legacyName !== '') {
            return $this->buildCityData($legacyName);
        }

        return null;
    }

    /** @param array{name?: string, ref?: string, subtitle?: string, label?: string, settlementRef?: string, warehouseCityRef?: string, hasLocalWarehouses?: bool} $data */
    private function normalizeCityData(array $data): ?array
    {
        $name = $this->normalizeCity((string) ($data['name'] ?? $data['label'] ?? ''));
        if ($name === '') {
            return null;
        }

        $subtitle = trim((string) ($data['subtitle'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));
        $settlementRef = trim((string) ($data['settlementRef'] ?? ''));
        $warehouseCityRef = trim((string) ($data['warehouseCityRef'] ?? $data['ref'] ?? ''));
        if ($settlementRef === '') {
            $settlementRef = $warehouseCityRef;
        }
        if ($warehouseCityRef === '') {
            $warehouseCityRef = $settlementRef;
        }

        return [
            'name' => $name,
            'ref' => $warehouseCityRef,
            'subtitle' => $subtitle,
            'label' => $label !== '' ? $label : $subtitle,
            'settlementRef' => $settlementRef,
            'warehouseCityRef' => $warehouseCityRef,
            'hasLocalWarehouses' => (bool) ($data['hasLocalWarehouses'] ?? false),
        ];
    }

    /** @return array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} */
    private function buildCityData(string $cityName): array
    {
        $cityName = $this->normalizeCity($cityName);
        if ($cityName === '') {
            return $this->defaultCityData();
        }

        if ($this->novaPoshtaClient->isConfigured()) {
            $matched = $this->novaPoshtaClient->findCityByName($cityName);
            if ($matched !== null) {
                $normalized = $this->normalizeCityData($matched);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return [
            'name' => $cityName,
            'ref' => '',
            'subtitle' => '',
            'label' => '',
            'settlementRef' => '',
            'warehouseCityRef' => '',
            'hasLocalWarehouses' => false,
        ];
    }

    /** @return array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} */
    private function defaultCityData(): array
    {
        return $this->buildCityData($this->defaultCity);
    }

    /** @param array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} $data */
    private function stageCityData(Request $request, array $data): void
    {
        $request->attributes->set(self::REQUEST_DATA_ATTRIBUTE, $data);
        $request->attributes->set(self::ATTACH_COOKIE_ATTRIBUTE, $data);
    }

    private function normalizeCity(string $city): string
    {
        $city = trim(preg_replace('/\s+/u', ' ', $city) ?? '');

        if ($city === '') {
            return '';
        }

        return mb_strlen($city) > 120 ? mb_substr($city, 0, 120) : $city;
    }
}
