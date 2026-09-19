<?php

namespace App\Service;

use App\Entity\ShipmentAddressType;

class NovaPoshtaDeliveryQuoteService
{
    private ?string $senderCityRef = null;

    public function __construct(
        private readonly NovaPoshtaClient $novaPoshtaClient,
        private readonly string $defaultCity = 'Івано-Франківськ',
        private readonly ?string $senderCityRefOverride = null,
        private readonly float $defaultPackageWeight = 1.0,
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
    ): ?float {
        if (!in_array($deliveryMethod, ['np_branch', 'np_postomat'], true)) {
            return 0.0;
        }

        if ($cityRecipientRef === '') {
            return null;
        }

        $senderCityRef = $this->resolveSenderCityRef();
        if ($senderCityRef === null) {
            return null;
        }

        $serviceType = $deliveryMethod === ShipmentAddressType::NovaPoshtaPostomat->value
            ? 'WarehousePostomat'
            : 'WarehouseWarehouse';

        return $this->novaPoshtaClient->calculateDeliveryPrice(
            $senderCityRef,
            $cityRecipientRef,
            $serviceType,
            max(0.1, $weightKg ?? $this->defaultPackageWeight),
            max(1.0, $declaredCost),
        );
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
