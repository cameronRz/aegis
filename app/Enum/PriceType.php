<?php

namespace App\Enum;

enum PriceType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}
