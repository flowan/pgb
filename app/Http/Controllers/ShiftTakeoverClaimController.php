<?php

namespace App\Http\Controllers;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
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

        $shiftTakeoverOffer->update([
            'status' => ShiftTakeoverOfferStatus::Claimed,
            'claimed_by_caregiver_id' => $claimer->id,
            'claimed_at' => now(),
            'resulting_exception_id' => $exception->id,
        ]);

        // notify in Task 8

        return back();
    }
}
