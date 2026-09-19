<?php

namespace App\Controller\Shop;

use App\Routing\ShopRoutes;
use App\Service\Cart\CartStorageService;
use App\Service\GeoIp\UserCityService;
use App\Service\NovaPoshtaClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CityController extends AbstractController
{
    public function __construct(
        private readonly UserCityService $userCityService,
        private readonly NovaPoshtaClient $novaPoshtaClient,
        private readonly CartStorageService $cartStorage,
    ) {
    }

    #[Route(
        '/{_locale}/city',
        name: 'shop_city_update',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function update(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('city_update', (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $name = trim((string) $request->request->get('city', $request->request->get('name', '')));
        if ($name === '') {
            return new JsonResponse(['success' => false, 'error' => 'City is required.'], Response::HTTP_BAD_REQUEST);
        }

        $cityData = [
            'name' => $name,
            'ref' => trim((string) $request->request->get('ref', '')),
            'subtitle' => trim((string) $request->request->get('subtitle', '')),
            'label' => trim((string) $request->request->get('label', '')),
            'settlementRef' => trim((string) $request->request->get('settlementRef', '')),
            'warehouseCityRef' => trim((string) $request->request->get('warehouseCityRef', '')),
            'hasLocalWarehouses' => filter_var($request->request->get('hasLocalWarehouses', false), FILTER_VALIDATE_BOOL),
        ];

        if ($cityData['ref'] === '' && $this->novaPoshtaClient->isConfigured()) {
            $matched = $this->novaPoshtaClient->findCityByName($name);
            if ($matched !== null) {
                $cityData = $matched;
            }
        }

        $previousCity = $this->userCityService->resolveData($request);
        $this->userCityService->rememberCityData($request, $cityData);
        $resolved = $this->userCityService->resolveData($request);

        $deliveryCleared = false;
        if ($this->hasCityChanged($previousCity, $resolved) && $this->cartStorage->getDeliveryData() !== null) {
            $this->cartStorage->clearDelivery();
            $deliveryCleared = true;
        }

        return new JsonResponse([
            'success' => true,
            'city' => $resolved,
            'deliveryCleared' => $deliveryCleared,
        ]);
    }

    #[Route(
        '/{_locale}/city/search',
        name: 'shop_city_search',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function search(Request $request): JsonResponse
    {
        if (!$this->novaPoshtaClient->isConfigured()) {
            return new JsonResponse(['items' => []], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $query = trim((string) $request->query->get('q', ''));
        if ($query === '') {
            return new JsonResponse(['items' => []]);
        }

        try {
            $items = $this->novaPoshtaClient->searchCities($query);
        } catch (\Throwable $exception) {
            return new JsonResponse(['items' => [], 'error' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['items' => $items]);
    }

    /** @param array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} $previous */
    /** @param array{name: string, ref: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} $current */
    private function hasCityChanged(array $previous, array $current): bool
    {
        return mb_strtolower(trim($previous['name'])) !== mb_strtolower(trim($current['name']))
            || trim($previous['ref']) !== trim($current['ref']);
    }
}
