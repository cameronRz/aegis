<?php

namespace App\Enum;

enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
