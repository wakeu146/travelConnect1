<?php

namespace App\Enums;

enum PaymentTransactionType: string
{
    case CHARGE = 'charge';
    case REFUND = 'refund';
}
