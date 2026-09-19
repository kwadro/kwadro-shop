<?php

namespace App\Service\Checkout;

use App\Entity\Payment;

final class ShipmentDepositCheckoutResult
{
    public const STATE_PAID = 'paid';
    public const STATE_PENDING = 'pending';
    public const STATE_UNAVAILABLE = 'unavailable';

    public function __construct(
        public readonly string $state,
        public readonly float $amount,
        public readonly ?Payment $payment = null,
        public readonly ?string $payUrl = null,
        public readonly ?string $gatewayMethod = null,
    ) {
    }

    public static function paid(float $amount): self
    {
        return new self(self::STATE_PAID, $amount);
    }

    public static function unavailable(float $amount): self
    {
        return new self(self::STATE_UNAVAILABLE, $amount);
    }

    public static function pending(
        float $amount,
        Payment $payment,
        string $payUrl,
        string $gatewayMethod,
    ): self {
        return new self(self::STATE_PENDING, $amount, $payment, $payUrl, $gatewayMethod);
    }
}
