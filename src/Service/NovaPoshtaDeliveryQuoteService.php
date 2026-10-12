<?php

namespace App\Service;

use App\Entity\ShipmentAddressType;
use Psr\Cache\CacheItemPoolInterface;

class NovaPoshtaDeliveryQuoteService
{
    private const QUOTE_CACHE_TTL = 21600;

    private ?string $senderCityRef = null;

    public function __construct(
        private readonly NovaPoshtaClient $novaPoshtaClient,
        private readonly string $defaultCity = 'Івано-Франківськ',
        private readonly ?string $senderCityRefOverride = null,
        private readonly float $defaultPackageWeight = 1.0,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->novaPoshtaClient->isConfigured() && $this->resolveSenderCityRef() !== null;
    }

    public function quoteForDeliveryMethod(
        string $deliveryMethod,
        string $cityRecipientRef,
        float $declaredCost,
        ?float $weightKg = null,
        ?string $senderCityRef = null,
    ): ?float {
        if (!in_array($deliveryMethod, ['np_branch', 'np_postomat'], true)) {
            return 0.0;
        }

        if ($cityRecipientRef === '') {
            return null;
        }

        $senderCityRef = $senderCityRef !== null && trim($senderCityRef) !== ''
            ? trim($senderCityRef)
            : $this->resolveSenderCityRef();
        if ($senderCityRef === null) {
            return null;
        }

        $serviceType = $deliveryMethod === ShipmentAddressType::NovaPoshtaPostomat->value
            ? 'WarehousePostomat'
            : 'WarehouseWarehouse';

        $weight = round(max(0.1, $weightKg ?? $this->defaultPackageWeight), 3);
        $cost = round(max(1.0, $declaredCost), 2);
        $cacheKey = 'np_quote_' . md5(implode('|', [$senderCityRef, $cityRecipientRef, $serviceType, $weight, $cost]));

        if ($this->cache === null) {
            return $this->novaPoshtaClient->calculateDeliveryPrice($senderCityRef, $cityRecipientRef, $serviceType, $weight, $cost);
        }

        $cached = $this->cache->getItem($cacheKey);
        if ($cached->isHit()) {
            return $cached->get();
        }

        $quote = $this->novaPoshtaClient->calculateDeliveryPrice($senderCityRef, $cityRecipientRef, $serviceType, $weight, $cost);
        if ($quote !== null) {
            $cached->set($quote)->expiresAfter(self::QUOTE_CACHE_TTL);
            $this->cache->save($cached);
        }

        return $quote;
    }

    private function resolveSenderCityRef(): ?string
    {
        if ($this->senderCityRefOverride !== null && $this->senderCityRefOverride !== '') {
            return $this->senderCityRefOverride;
        }

        if ($this->senderCityRef !== null) {
            return $this->senderCityRef !== '' ? $this->senderCityRef : null;
        }

        if (!$this->novaPoshtaClient->isConfigured()) {
            $this->senderCityRef = '';

            return null;
        }

        $city = $this->novaPoshtaClient->findCityByName($this->defaultCity);
        $this->senderCityRef = $city['ref'] ?? '';

        return $this->senderCityRef !== '' ? $this->senderCityRef : null;
    }
}
