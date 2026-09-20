<?php

namespace App\Service;

use App\Service\GeoIp\UkrainianCityNameNormalizer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NovaPoshtaClient
{
    private const API_URL = 'https://api.novaposhta.ua/v2.0/json/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UkrainianCityNameNormalizer $cityNameNormalizer,
        private readonly ?string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /** @return list<array{ref: string, name: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool}> */
    public function searchCities(string $query, int $limit = 20): array
    {
        if (!$this->isConfigured() || trim($query) === '') {
            return [];
        }
        $query = $this->cityNameNormalizer->normalize(trim($query));

        $response = $this->request('Address', 'searchSettlements', [
            'CityName' => $query,
            'Limit' => $limit,
        ]);

        $addresses = $response[0]['Addresses'] ?? [];

        return array_values(array_map([self::class, 'formatCitySearchResult'], $addresses));
    }

    /** @return list<array{ref: string, name: string, number: string, category: string}> */
    public function getWarehouses(string $cityRef, ?string $category = null): array
    {
        if (!$this->isConfigured() || $cityRef === '') {
            return [];
        }

        $response = $this->request('AddressGeneral', 'getWarehouses', [
            'CityRef' => $cityRef,
            'Limit' => 500,
            'Language' => 'UA',
        ]);

        $warehouses = [];
        foreach ($response as $item) {
            $warehouseCategory = (string) ($item['CategoryOfWarehouse'] ?? '');
            if ($category === 'postomat' && $warehouseCategory !== 'Postomat') {
                continue;
            }
            if ($category === 'branch' && $warehouseCategory === 'Postomat') {
                continue;
            }

            $warehouses[] = [
                'ref' => (string) ($item['Ref'] ?? ''),
                'name' => (string) ($item['Description'] ?? ''),
                'number' => (string) ($item['Number'] ?? ''),
                'category' => $warehouseCategory,
            ];
        }

        usort($warehouses, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $warehouses;
    }

    public function calculateDeliveryPrice(
        string $citySenderRef,
        string $cityRecipientRef,
        string $serviceType,
        float $weightKg,
        float $declaredCost,
    ): float {
        if (!$this->isConfigured() || $citySenderRef === '' || $cityRecipientRef === '') {
            throw new \InvalidArgumentException('Nova Poshta delivery price requires configured API and city references.');
        }

        $response = $this->request('InternetDocument', 'getDocumentPrice', [
            'CitySender' => $citySenderRef,
            'CityRecipient' => $cityRecipientRef,
            'ServiceType' => $serviceType,
            'Weight' => number_format(max(0.1, $weightKg), 2, '.', ''),
            'Cost' => (string) max(1, (int) round($declaredCost)),
            'CargoType' => 'Parcel',
            'SeatsAmount' => '1',
        ]);

        $cost = $response[0]['Cost'] ?? null;
        if (!is_numeric($cost)) {
            throw new \RuntimeException('Nova Poshta API did not return delivery cost.');
        }

        return round((float) $cost, 2);
    }

    /** @return array{ref: string, name: string}|null */
    public function findCityByRef(string $ref): ?array
    {
        if (!$this->isConfigured() || self::isEmptyRef($ref)) {
            return null;
        }

        $response = $this->request('Address', 'getCities', [
            'Ref' => $ref,
            'Limit' => 1,
        ]);

        $item = $response[0] ?? null;
        if (!\is_array($item)) {
            return null;
        }

        $name = trim((string) ($item['Description'] ?? ''));
        if ($name === '') {
            return null;
        }

        return [
            'ref' => (string) ($item['Ref'] ?? $ref),
            'name' => $name,
        ];
    }

    /** @return array{ref: string, name: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool}|null */
    public function findCityByName(string $name): ?array
    {
        $originalName = trim($name);
        $searchName = $this->cityNameNormalizer->normalize($originalName);
        $cities = $this->searchCities($searchName, 5);
        if ($cities === []) {
            return null;
        }

        if ($this->cityNameNormalizer->wasAliased($originalName, $searchName)) {
            return $cities[0];
        }

        foreach ($cities as $city) {
            if (mb_stripos($city['name'], $searchName) !== false || mb_stripos($city['name'], $originalName) !== false) {
                return $city;
            }
        }

        return $cities[0];
    }

    /** @param array<string, mixed> $item */
    /** @return array{ref: string, name: string, subtitle: string, label: string, settlementRef: string, warehouseCityRef: string, hasLocalWarehouses: bool} */
    private static function formatCitySearchResult(array $item): array
    {
        $settlementRef = trim((string) ($item['Ref'] ?? ''));
        $deliveryCityRef = trim((string) ($item['DeliveryCity'] ?? ''));
        $warehouseCityRef = self::resolveWarehouseCityRef($deliveryCityRef, $settlementRef);
        $hasLocalWarehouses = (int) ($item['Warehouses'] ?? 0) > 0;

        $present = trim((string) ($item['Present'] ?? ''));
        $name = trim((string) ($item['MainDescription'] ?? ''));
        if ($name === '') {
            $name = $present;
        }

        $area = trim((string) ($item['AreaDescription'] ?? ''));
        $region = trim((string) ($item['RegionDescription'] ?? ''));
        $settlementType = trim((string) ($item['SettlementTypeDescription'] ?? $item['SettlementTypeCode'] ?? ''));
        $parentRegion = trim((string) ($item['ParentRegionDescription'] ?? ''));

        $detailParts = array_values(array_filter([$settlementType, $area, $region, $parentRegion]));
        $details = implode(', ', $detailParts);

        $label = $present !== '' ? $present : $name;
        if ($label === $name && $details !== '') {
            $label = $name . ', ' . $details;
        }

        $subtitle = $details;
        if ($present !== '' && mb_strtolower($present) !== mb_strtolower($name)) {
            $subtitle = $present;
        }

        return [
            'ref' => $warehouseCityRef,
            'name' => $name,
            'subtitle' => $subtitle,
            'label' => $label,
            'settlementRef' => $settlementRef,
            'warehouseCityRef' => $warehouseCityRef,
            'hasLocalWarehouses' => $hasLocalWarehouses,
        ];
    }

    private static function isEmptyRef(string $ref): bool
    {
        return $ref === '' || $ref === '00000000-0000-0000-0000-000000000000';
    }

    private static function resolveWarehouseCityRef(string $deliveryCityRef, string $settlementRef): string
    {
        if ($settlementRef === '') {
            return self::isEmptyRef($deliveryCityRef) ? '' : $deliveryCityRef;
        }

        if (self::isEmptyRef($deliveryCityRef) || $deliveryCityRef === $settlementRef) {
            return $settlementRef;
        }

        return $deliveryCityRef;
    }

    /** @return array<string, mixed> */
    public function saveInternetDocument(array $properties): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Nova Poshta API key is not configured.');
        }

        $response = $this->request('InternetDocumentGeneral', 'save', $properties);
        $item = $response[0] ?? null;
        if (!\is_array($item)) {
            throw new \RuntimeException('Nova Poshta API did not return waybill data.');
        }

        return $item;
    }

    /**
     * @return array{recipientRef: string, contactRef: string}
     */
    public function createRecipient(string $firstName, string $lastName, string $middleName, string $phone, string $email = ''): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Nova Poshta API key is not configured.');
        }

        $properties = [
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'MiddleName' => $middleName,
            'Phone' => $phone,
            'CounterpartyType' => 'PrivatePerson',
            'CounterpartyProperty' => 'Recipient',
        ];

        if ($email !== '') {
            $properties['Email'] = $email;
        }

        $response = $this->request('CounterpartyGeneral', 'save', $properties);
        $item = $response[0] ?? null;
        if (!\is_array($item)) {
            throw new \RuntimeException('Nova Poshta API did not return recipient data.');
        }

        $recipientRef = trim((string) ($item['Ref'] ?? ''));
        $contactRef = trim((string) ($item['ContactPerson']['data'][0]['Ref'] ?? ''));
        if ($recipientRef === '' || $contactRef === '') {
            throw new \RuntimeException('Nova Poshta API returned incomplete recipient references.');
        }

        return [
            'recipientRef' => $recipientRef,
            'contactRef' => $contactRef,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getSenderCounterparties(int $page = 1): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Nova Poshta API key is not configured.');
        }

        return $this->request('Counterparty', 'getCounterparties', [
            'CounterpartyProperty' => 'Sender',
            'Page' => (string) max(1, $page),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function getCounterpartyContactPersons(string $counterpartyRef, int $page = 1): array
    {
        if (!$this->isConfigured() || self::isEmptyRef($counterpartyRef)) {
            throw new \InvalidArgumentException('Counterparty reference is required.');
        }

        return $this->request('CounterpartyGeneral', 'getCounterpartyContactPersons', [
            'Ref' => $counterpartyRef,
            'Page' => (string) max(1, $page),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function getCounterpartyAddresses(string $counterpartyRef, int $page = 1): array
    {
        if (!$this->isConfigured() || self::isEmptyRef($counterpartyRef)) {
            throw new \InvalidArgumentException('Counterparty reference is required.');
        }

        return $this->request('CounterpartyGeneral', 'getCounterpartyAddresses', [
            'Ref' => $counterpartyRef,
            'Page' => (string) max(1, $page),
        ]);
    }

    /** @return list<mixed> */
    private function request(string $model, string $method, array $properties): array
    {
        $payload = [
            'apiKey' => $this->apiKey,
            'modelName' => $model,
            'calledMethod' => $method,
            'methodProperties' => $properties,
        ];
//        var_dump($payload);
//        exit;

        $response = $this->httpClient->request('POST', self::API_URL, [
            'json' => $payload,
            'timeout' => 15,
        ])->toArray(false);

        if (!($response['success'] ?? false)) {
            $errors = $response['errors'] ?? ['Nova Poshta API error'];
            throw new \RuntimeException(implode('; ', (array) $errors));
        }

        return $response['data'] ?? [];
    }
}
