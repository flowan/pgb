<?php

namespace App\Observers;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\Schedule;

class ScheduleObserver
{
    public function deleting(Schedule $schedule): void
    {
        AvailabilitySlot::where('schedule_id', $schedule->id)->update([
            'status' => AvailabilitySlotStatus::Open->value,
            'claimed_by' => null,
            'claimed_at' => null,
            'schedule_id' => null,
        ]);
    }
}
