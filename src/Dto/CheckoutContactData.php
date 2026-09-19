<?php

namespace App\Dto;

class CheckoutContactData
{
    public ?string $customerName = null;

    public ?string $customerPhone = null;

    public ?string $customerEmail = null;

    public bool $doNotCall = false;
}
