<?php

namespace App\Http\Controllers;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Http\RedirectResponse;

class AvailabilityClaimController extends Controller
{
    public function store(AvailabilitySlot $availabilitySlot): RedirectResponse
    {
        $user = auth()->user();

        if ($user->role !== UserRole::Caregiver) {
            abort(403);
        }

        $caregiver = Caregiver::where('user_id', $user->id)
            ->where('client_id', $availabilitySlot->client_id)
            ->first();

        if (! $caregiver) {
            abort(403);
        }

        if ($availabilitySlot->status !== AvailabilitySlotStatus::Open) {
            abort(409);
        }

        if ($availabilitySlot->day_of_week !== null) {
            $schedule = Schedule::create([
                'client_id' => $availabilitySlot->client_id,
                'caregiver_id' => $caregiver->id,
                'day_of_week' => $availabilitySlot->day_of_week,
                'start_time' => $availabilitySlot->start_time,
                'end_time' => $availabilitySlot->end_time,
            ]);

            $availabilitySlot->schedule_id = $schedule->id;
        } elseif ($availabilitySlot->date !== null) {
            $exception = ScheduleException::create([
                'client_id' => $availabilitySlot->client_id,
                'caregiver_id' => $caregiver->id,
                'date' => $availabilitySlot->date,
                'start_time' => $availabilitySlot->start_time,
                'end_time' => $availabilitySlot->end_time,
                'type' => ScheduleExceptionType::Added,
            ]);

            $availabilitySlot->schedule_exception_id = $exception->id;
        }

        $availabilitySlot->status = AvailabilitySlotStatus::Claimed;
        $availabilitySlot->claimed_by = $caregiver->id;
        $availabilitySlot->claimed_at = now();
        $availabilitySlot->save();

        return redirect()->route('my-schedule');
    }
}
