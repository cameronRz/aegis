<?php

namespace App\Enum;

enum Tier: string
{
    case SiteAdmin = 'site_admin';
    case Admin = 'admin';
    case User = 'user';
}
