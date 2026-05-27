<?php

namespace App\Enums;

enum AvailabilitySlotStatus: string
{
    case Open = 'open';
    case Claimed = 'claimed';
}
