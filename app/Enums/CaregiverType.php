<?php

namespace App\Enums;

enum CaregiverType: string
{
    case Parent = 'parent';
    case CareWorker = 'care_worker';
    case DayCare = 'day_care';
    case Zzp = 'zzp';
    case Other = 'other';
}
