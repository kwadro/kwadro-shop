<?php

namespace App\Service\Checkout;

use App\Entity\Payment;

final class ShipmentDepositCheckoutResult
{
    public const STATE_PAID = 'paid';
    public const STATE_PENDING = 'pending';
    public const STATE_UNAVAILABLE = 'unavailable';

    /**
     * @param list<ShipmentDepositGatewayOption> $gateways
     */
    public function __construct(
        public readonly string $state,
        public readonly float $amount,
        public readonly array $gateways = [],
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

    /**
     * @param list<ShipmentDepositGatewayOption> $gateways
     */
    public static function pending(float $amount, array $gateways): self
    {
        return new self(self::STATE_PENDING, $amount, $gateways);
    }

    public function gateway(string $method): ?ShipmentDepositGatewayOption
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->method === $method) {
                return $gateway;
            }
        }

        return null;
    }

    public function hasGateways(): bool
    {
        return $this->gateways !== [];
    }

    /** @deprecated Use gateway() — kept for transitional callers */
    public function getPayment(): ?Payment
    {
        return $this->gateways[0]->payment ?? null;
    }
}
