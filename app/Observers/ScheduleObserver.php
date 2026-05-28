<?php

namespace App\Observers;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\ShiftTakeoverRequestStatus;
use App\Models\AvailabilitySlot;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;
use App\Models\ShiftTakeoverRequest;

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

        ShiftTakeoverOffer::where('schedule_id', $schedule->id)
            ->where('status', ShiftTakeoverOfferStatus::Open)
            ->update(['status' => ShiftTakeoverOfferStatus::Cancelled]);

        ShiftSwapRequest::where('status', ShiftSwapRequestStatus::Pending)
            ->where(function ($query) use ($schedule) {
                $query->where('requester_schedule_id', $schedule->id)
                    ->orWhere('target_schedule_id', $schedule->id);
            })
            ->update(['status' => ShiftSwapRequestStatus::Cancelled]);

        OpenSwapRequest::where('schedule_id', $schedule->id)
            ->where('status', OpenSwapRequestStatus::Open)
            ->update(['status' => OpenSwapRequestStatus::Cancelled]);

        OpenSwapOffer::where('offered_schedule_id', $schedule->id)
            ->where('status', OpenSwapOfferStatus::Pending)
            ->update(['status' => OpenSwapOfferStatus::Withdrawn]);

        ShiftTakeoverRequest::where('target_schedule_id', $schedule->id)
            ->where('status', ShiftTakeoverRequestStatus::Pending)
            ->update(['status' => ShiftTakeoverRequestStatus::Cancelled]);
    }
}
