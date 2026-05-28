<?php

namespace App\Http\Controllers;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Http\Requests\ShiftTakeoverOfferRequest;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShiftTakeoverOfferController extends Controller
{
    public function store(ShiftTakeoverOfferRequest $request): RedirectResponse
    {
        $this->authorize('create', ShiftTakeoverOffer::class);

        $scheduleId = $request->validated('schedule_id');
        $scheduleExceptionId = $request->validated('schedule_exception_id');
        $date = $request->validated('date');

        if ($scheduleId) {
            $schedule = Schedule::findOrFail($scheduleId);
            $clientId = $schedule->client_id;
        } else {
            $exception = ScheduleException::findOrFail($scheduleExceptionId);
            $clientId = $exception->client_id;
        }

        $caregiver = Caregiver::where('user_id', auth()->id())
            ->where('client_id', $clientId)
            ->first();

        if (! $caregiver) {
            abort(403);
        }

        $duplicateQuery = ShiftTakeoverOffer::where('status', ShiftTakeoverOfferStatus::Open)
            ->where('date', $date);

        if ($scheduleId) {
            $duplicateQuery->where('schedule_id', $scheduleId);
        } else {
            $duplicateQuery->where('schedule_exception_id', $scheduleExceptionId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'schedule_id' => 'An open takeover offer already exists for this shift.',
            ]);
        }

        ShiftTakeoverOffer::create([
            'schedule_id' => $scheduleId,
            'schedule_exception_id' => $scheduleExceptionId,
            'date' => $date,
            'offered_by_caregiver_id' => $caregiver->id,
            'status' => ShiftTakeoverOfferStatus::Open,
            'notes' => $request->validated('notes'),
        ]);

        // notify in Task 8

        return back();
    }

    public function destroy(ShiftTakeoverOffer $shiftTakeoverOffer): RedirectResponse
    {
        $this->authorize('delete', $shiftTakeoverOffer);

        if ($shiftTakeoverOffer->status !== ShiftTakeoverOfferStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'Only open offers can be cancelled.',
            ]);
        }

        $shiftTakeoverOffer->update(['status' => ShiftTakeoverOfferStatus::Cancelled]);

        // notify in Task 8

        return back();
    }
}
