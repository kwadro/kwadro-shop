<?php

namespace App\Entity;

enum OrderStatus: string
{
    case Created = 'created';
    case InProcess = 'in_process';
    case AwaitingDepositForShipment = 'awaiting_deposit_for_shipment';
    case DepositPaid = 'deposit_paid';
    case Paid = 'paid';
}
