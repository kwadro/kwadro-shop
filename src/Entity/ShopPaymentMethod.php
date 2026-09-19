<?php

namespace App\Entity;

final class ShopPaymentMethod
{
    public const OnDelivery = 'on_delivery';
    public const Privatbank = 'privatbank';
    public const Monobank = 'monobank';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::OnDelivery,
            self::Privatbank,
            self::Monobank,
        ];
    }
}
