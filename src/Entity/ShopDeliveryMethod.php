<?php

namespace App\Entity;

final class ShopDeliveryMethod
{
    public const Courier = 'courier';
    public const NovaPoshtaBranch = 'np_branch';
    public const NovaPoshtaPostomat = 'np_postomat';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::Courier,
            self::NovaPoshtaBranch,
            self::NovaPoshtaPostomat,
        ];
    }
}
