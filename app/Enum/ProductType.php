<?php

namespace App\Enum;

enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case Subscription = 'subscription';
}
