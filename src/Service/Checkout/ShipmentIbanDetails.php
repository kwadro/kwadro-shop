<?php

namespace App\Service\Checkout;

final class ShipmentIbanDetails
{
    public const BANK_MONOBANK = 'monobank';
    public const BANK_PRIVATBANK = 'privatbank';

    public function __construct(
        public readonly string $iban,
        public readonly string $recipient,
        public readonly string $bankName,
        public readonly string $edrpou,
        public readonly string $paymentPurpose,
        public readonly ?string $bankKey = null,
        public readonly string $title = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->iban !== '';
    }
}
