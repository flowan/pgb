<?php

namespace App\Http\Controllers;

use App\Enums\ShiftTakeoverRequestStatus;
use App\Models\ShiftTakeoverRequest;
use App\Services\ShiftReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftTakeoverRequestResponseController extends Controller
{
    public function __construct(private ShiftReassignmentService $reassignmentService) {}

    public function accept(ShiftTakeoverRequest $shiftTakeoverRequest): RedirectResponse
    {
        $this->authorize('respond', $shiftTakeoverRequest);
        $this->ensurePending($shiftTakeoverRequest);

        $source = $shiftTakeoverRequest->target_schedule_id
            ? $shiftTakeoverRequest->targetSchedule
            : $shiftTakeoverRequest->targetScheduleException;

        if (! $source) {
            abort(404);
        }

        $exception = $this->reassignmentService->reassign(
            $source,
            $shiftTakeoverRequest->target_date->toDateString(),
            $shiftTakeoverRequest->requester_caregiver_id,
        );

        $shiftTakeoverRequest->update([
            'status' => ShiftTakeoverRequestStatus::Accepted,
            'responded_at' => now(),
            'resulting_exception_id' => $exception->id,
        ]);

        return back();
    }

    public function decline(Request $request, ShiftTakeoverRequest $shiftTakeoverRequest): RedirectResponse
    {
        $this->authorize('respond', $shiftTakeoverRequest);
        $this->ensurePending($shiftTakeoverRequest);

        $validated = $request->validate([
            'decline_reason' => ['nullable', 'string'],
        ]);

        $shiftTakeoverRequest->update([
            'status' => ShiftTakeoverRequestStatus::Declined,
            'responded_at' => now(),
            'decline_reason' => $validated['decline_reason'] ?? null,
        ]);

        return back();
    }

    private function ensurePending(ShiftTakeoverRequest $r): void
    {
        if ($r->status !== ShiftTakeoverRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Dit verzoek is niet meer open.',
            ]);
        }
    }
}
