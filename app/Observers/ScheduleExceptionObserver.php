<?php

namespace App\Observers;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\ScheduleException;

class ScheduleExceptionObserver
{
    public function deleting(ScheduleException $exception): void
    {
        AvailabilitySlot::where('schedule_exception_id', $exception->id)->update([
            'status' => AvailabilitySlotStatus::Open->value,
            'claimed_by' => null,
            'claimed_at' => null,
            'schedule_exception_id' => null,
        ]);
    }
}
