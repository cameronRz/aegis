<?php

namespace App\Enum;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Paused = 'paused';
}
