<?php

namespace App\Service\Checkout;

use App\Entity\ShopDeliveryMethod;
use App\Entity\ShopPaymentMethod;

final class CheckoutPaymentMethodResolver
{
    /** @return list<string> */
    public function deliveryMethodsAllowingOnDelivery(): array
    {
        return [ShopDeliveryMethod::NovaPoshtaBranch];
    }

    public function isOnDeliveryAllowedForDelivery(string $deliveryMethod): bool
    {
        return in_array($deliveryMethod, $this->deliveryMethodsAllowingOnDelivery(), true);
    }

    /**
     * @param list<string> $methods
     *
     * @return list<string>
     */
    public function filterByDeliveryMethod(array $methods, string $deliveryMethod): array
    {
        if ($this->isOnDeliveryAllowedForDelivery($deliveryMethod)) {
            return $methods;
        }

        return array_values(array_filter(
            $methods,
            static fn (string $method): bool => $method !== ShopPaymentMethod::OnDelivery,
        ));
    }
}
