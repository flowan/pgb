<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapRequestStatus;
use App\Models\ShiftSwapRequest;
use App\Services\ShiftReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftSwapResponseController extends Controller
{
    public function __construct(private ShiftReassignmentService $reassignmentService) {}

    public function accept(ShiftSwapRequest $shiftSwapRequest): RedirectResponse
    {
        $this->authorize('respond', $shiftSwapRequest);

        if ($shiftSwapRequest->status !== ShiftSwapRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'This swap request is no longer pending.',
            ]);
        }

        $requesterSource = $shiftSwapRequest->requester_schedule_id
            ? $shiftSwapRequest->requesterSchedule
            : $shiftSwapRequest->requesterScheduleException;

        $targetSource = $shiftSwapRequest->target_schedule_id
            ? $shiftSwapRequest->targetSchedule
            : $shiftSwapRequest->targetScheduleException;

        if (! $requesterSource || ! $targetSource) {
            abort(404);
        }

        // target caregiver gets requester's shift
        $exceptionForRequesterShift = $this->reassignmentService->reassign(
            $requesterSource,
            $shiftSwapRequest->requester_date->toDateString(),
            $shiftSwapRequest->target_caregiver_id,
        );

        // requester caregiver gets target's shift
        $exceptionForTargetShift = $this->reassignmentService->reassign(
            $targetSource,
            $shiftSwapRequest->target_date->toDateString(),
            $shiftSwapRequest->requester_caregiver_id,
        );

        $shiftSwapRequest->update([
            'status' => ShiftSwapRequestStatus::Accepted,
            'responded_at' => now(),
            'resulting_exception_ids' => [
                $exceptionForRequesterShift->id,
                $exceptionForTargetShift->id,
            ],
        ]);

        \App\Notifications\ShiftSwapResponded::notify($shiftSwapRequest, true);

        return back();
    }

    public function decline(Request $request, ShiftSwapRequest $shiftSwapRequest): RedirectResponse
    {
        $this->authorize('respond', $shiftSwapRequest);

        if ($shiftSwapRequest->status !== ShiftSwapRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'This swap request is no longer pending.',
            ]);
        }

        $validated = $request->validate([
            'decline_reason' => ['nullable', 'string'],
        ]);

        $shiftSwapRequest->update([
            'status' => ShiftSwapRequestStatus::Declined,
            'responded_at' => now(),
            'decline_reason' => $validated['decline_reason'] ?? null,
        ]);

        \App\Notifications\ShiftSwapResponded::notify($shiftSwapRequest, false);

        return back();
    }
}
