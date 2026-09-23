<?php

namespace App\Service\Checkout;

use App\Entity\Payment;

final class ShipmentDepositGatewayOption
{
    /**
     * @param array{data: string, signature: string}|null $liqpayWidget
     */
    public function __construct(
        public readonly string $method,
        public readonly Payment $payment,
        public readonly string $payUrl,
        public readonly ?array $liqpayWidget = null,
    ) {
    }
}
