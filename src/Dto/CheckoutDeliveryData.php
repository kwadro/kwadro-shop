<?php

namespace App\Dto;

class CheckoutDeliveryData
{
    /** @var 'courier'|'np_branch'|'np_postomat'|null */
    public ?string $deliveryMethod = 'courier';

    public ?string $courierAddress = null;

    public ?string $npCityRef = null;

    public ?string $npCityName = null;

    public ?string $npWarehouseRef = null;

    public ?string $npWarehouseName = null;

    public ?float $deliveryCost = null;
}
