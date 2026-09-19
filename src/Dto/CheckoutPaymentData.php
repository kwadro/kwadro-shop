<?php

namespace App\Dto;

class CheckoutPaymentData
{
    /** @var 'on_delivery'|'privatbank'|'monobank'|null */
    public ?string $paymentMethod = 'on_delivery';

    public bool $doNotCall = false;
}
