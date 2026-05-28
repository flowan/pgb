<?php

namespace App\Enums;

enum OpenSwapOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
