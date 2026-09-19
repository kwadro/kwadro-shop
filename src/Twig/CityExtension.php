<?php

namespace App\Twig;

use App\Service\GeoIp\UserCityService;
use App\Service\NovaPoshtaClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CityExtension extends AbstractExtension
{
    public function __construct(
        private readonly UserCityService $userCityService,
        private readonly RequestStack $requestStack,
        private readonly NovaPoshtaClient $novaPoshtaClient,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('shop_city', [$this, 'getCity']),
            new TwigFunction('shop_city_data', [$this, 'getCityData']),
            new TwigFunction('nova_poshta_enabled', [$this, 'isNovaPoshtaEnabled']),
            new TwigFunction('shop_popular_cities', [$this, 'getPopularCities']),
        ];
    }

    public function getCity(): string
    {
        return $this->getCityData()['name'];
    }

    /** @return array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} */
    public function getCityData(): array
    {
        return $this->userCityService->resolveData($this->requestStack->getCurrentRequest());
    }

    /** @return list<string> */
    public function getPopularCities(): array
    {
        return UserCityService::POPULAR_CITIES;
    }

    public function isNovaPoshtaEnabled(): bool
    {
        return $this->novaPoshtaClient->isConfigured();
    }
}
