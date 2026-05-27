<?php

namespace App\Enums;

enum ScheduleExceptionType: string
{
    case Cancelled = 'cancelled';
    case Modified = 'modified';
    case Added = 'added';
}
