<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapRequestStatus;
use App\Http\Requests\ShiftSwapRequestRequest;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftSwapRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShiftSwapRequestController extends Controller
{
    public function store(ShiftSwapRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', ShiftSwapRequest::class);

        $requesterScheduleId = $request->validated('requester_schedule_id');
        $requesterScheduleExceptionId = $request->validated('requester_schedule_exception_id');

        if ($requesterScheduleId) {
            $requesterSource = Schedule::findOrFail($requesterScheduleId);
        } else {
            $requesterSource = ScheduleException::findOrFail($requesterScheduleExceptionId);
        }

        $clientId = $requesterSource->client_id;

        $requesterCaregiver = Caregiver::where('user_id', auth()->id())
            ->where('client_id', $clientId)
            ->first();

        if (! $requesterCaregiver) {
            abort(403);
        }

        $targetCaregiver = Caregiver::findOrFail($request->validated('target_caregiver_id'));

        if ($targetCaregiver->client_id !== $clientId) {
            throw ValidationException::withMessages([
                'target_caregiver_id' => 'The target caregiver must work for the same client.',
            ]);
        }

        ShiftSwapRequest::create([
            'requester_caregiver_id' => $requesterCaregiver->id,
            'requester_schedule_id' => $requesterScheduleId,
            'requester_schedule_exception_id' => $requesterScheduleExceptionId,
            'requester_date' => $request->validated('requester_date'),
            'target_caregiver_id' => $targetCaregiver->id,
            'target_schedule_id' => $request->validated('target_schedule_id'),
            'target_schedule_exception_id' => $request->validated('target_schedule_exception_id'),
            'target_date' => $request->validated('target_date'),
            'status' => ShiftSwapRequestStatus::Pending,
        ]);

        // notify in Task 8

        return back();
    }

    public function destroy(ShiftSwapRequest $shiftSwapRequest): RedirectResponse
    {
        $this->authorize('delete', $shiftSwapRequest);

        if ($shiftSwapRequest->status !== ShiftSwapRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending swap requests can be cancelled.',
            ]);
        }

        $shiftSwapRequest->update(['status' => ShiftSwapRequestStatus::Cancelled]);

        // notify in Task 8

        return back();
    }
}
