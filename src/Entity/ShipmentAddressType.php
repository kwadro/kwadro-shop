<?php

namespace App\Entity;

enum ShipmentAddressType: string
{
    case Courier = 'courier';
    case NovaPoshtaBranch = 'np_branch';
    case NovaPoshtaPostomat = 'np_postomat';
}
