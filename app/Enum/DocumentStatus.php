<?php

namespace App\Enum;

enum DocumentStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
