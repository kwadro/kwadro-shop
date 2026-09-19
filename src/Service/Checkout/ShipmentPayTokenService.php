<?php

namespace App\Service\Checkout;

use App\Entity\Order;
use App\Entity\Payment;

final class ShipmentPayTokenService
{
    public function __construct(
        private readonly string $appSecret,
    ) {
    }

    public function generate(Order $order, Payment $payment): string
    {
        return substr(hash_hmac(
            'sha256',
            sprintf('%d:%d:%s', $order->getId() ?? 0, $payment->getId() ?? 0, $payment->getGatewayReference()),
            $this->appSecret,
        ), 0, 40);
    }

    public function matches(Order $order, Payment $payment, string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        return hash_equals($this->generate($order, $payment), $token);
    }
}
