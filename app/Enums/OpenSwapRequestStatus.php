<?php

namespace App\Enums;

enum OpenSwapRequestStatus: string
{
    case Open = 'open';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
