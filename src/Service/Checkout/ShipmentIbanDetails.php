<?php

namespace App\Service\Checkout;

final class ShipmentIbanDetails
{
    public function __construct(
        public readonly string $iban,
        public readonly string $recipient,
        public readonly string $bankName,
        public readonly string $edrpou,
        public readonly string $paymentPurpose,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->iban !== '';
    }
}
