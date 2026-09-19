<?php

namespace App\Entity;

enum CartItemStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
