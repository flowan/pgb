<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleExceptionType;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
use App\Models\ScheduleException;
use App\Models\ShiftTakeoverOffer;
use App\Services\ShiftReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShiftTakeoverClaimController extends Controller
{
    public function __construct(private ShiftReassignmentService $reassignmentService) {}

    public function store(ShiftTakeoverOffer $shiftTakeoverOffer): RedirectResponse
    {
        $this->authorize('claim', $shiftTakeoverOffer);

        if ($shiftTakeoverOffer->status !== ShiftTakeoverOfferStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'This offer is no longer open.',
            ]);
        }

        $source = $shiftTakeoverOffer->schedule_id
            ? $shiftTakeoverOffer->schedule
            : $shiftTakeoverOffer->scheduleException;

        if (! $source) {
            abort(404);
        }

        $claimer = Caregiver::where('user_id', auth()->id())
            ->where('client_id', $source->client_id)
            ->first();

        if (! $claimer) {
            abort(403);
        }

        $exception = $this->reassignmentService->reassign(
            $source,
            $shiftTakeoverOffer->date->toDateString(),
            $claimer->id,
        );

        // If the offer was created from a sick-cancellation, remove that cancellation
        // so the slot doesn't show as both cancelled-sick AND covered by the claimer.
        if ($shiftTakeoverOffer->schedule_id) {
            ScheduleException::where('schedule_id', $shiftTakeoverOffer->schedule_id)
                ->whereDate('date', $shiftTakeoverOffer->date->toDateString())
                ->where('type', ScheduleExceptionType::Cancelled)
                ->where('due_to_sickness', true)
                ->delete();
        }

        $shiftTakeoverOffer->update([
            'status' => ShiftTakeoverOfferStatus::Claimed,
            'claimed_by_caregiver_id' => $claimer->id,
            'claimed_at' => now(),
            'resulting_exception_id' => $exception->id,
        ]);

        \App\Notifications\ShiftTakenOver::notify($shiftTakeoverOffer);

        return back();
    }
}
