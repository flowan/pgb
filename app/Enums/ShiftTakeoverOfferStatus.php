<?php

namespace App\Enums;

enum ShiftTakeoverOfferStatus: string
{
    case Open = 'open';
    case Claimed = 'claimed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
