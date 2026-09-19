<?php

namespace App\Entity;

enum OrderEmailEvent: string
{
    case OrderCreated = 'order_created';
    case WaitPaymentForShipment = 'wait_payment_for_shipment';
    case OrderPaid = 'order_paid';
    case RegisterUser = 'register_user';
}
