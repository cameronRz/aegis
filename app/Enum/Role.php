<?php

namespace App\Enum;

enum Role: string
{
    case SiteAdmin = 'site_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case User = 'user';
}
