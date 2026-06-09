<?php

namespace App\Enum;

enum BillingInterval: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
