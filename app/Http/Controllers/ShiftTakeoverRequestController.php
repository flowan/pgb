<?php

namespace App\Http\Controllers;

use App\Enums\ShiftTakeoverRequestStatus;
use App\Http\Requests\ShiftTakeoverRequestRequest;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftTakeoverRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShiftTakeoverRequestController extends Controller
{
    public function store(ShiftTakeoverRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', ShiftTakeoverRequest::class);
        $user = auth()->user();

        $target = Caregiver::findOrFail($request->target_caregiver_id);

        $requester = Caregiver::where('user_id', $user->id)
            ->where('client_id', $target->client_id)
            ->first();

        if (! $requester) {
            abort(403);
        }
        if ($requester->id === $target->id) {
            throw ValidationException::withMessages([
                'target_caregiver_id' => 'Je kunt jouw eigen shift niet overnemen.',
            ]);
        }

        // Source shift must belong to target caregiver
        $sourceClientId = $request->target_schedule_id
            ? Schedule::findOrFail($request->target_schedule_id)->client_id
            : ScheduleException::findOrFail($request->target_schedule_exception_id)->client_id;
        if ($sourceClientId !== $target->client_id) {
            throw ValidationException::withMessages([
                'target_schedule_id' => 'De shift hoort niet bij de target caregiver.',
            ]);
        }

        ShiftTakeoverRequest::create([
            'requester_caregiver_id' => $requester->id,
            'target_caregiver_id' => $target->id,
            'target_schedule_id' => $request->target_schedule_id,
            'target_schedule_exception_id' => $request->target_schedule_exception_id,
            'target_date' => $request->target_date,
            'message' => $request->message,
            'status' => ShiftTakeoverRequestStatus::Pending,
        ]);

        return back();
    }

    public function destroy(ShiftTakeoverRequest $shiftTakeoverRequest): RedirectResponse
    {
        $this->authorize('delete', $shiftTakeoverRequest);
        if ($shiftTakeoverRequest->status !== ShiftTakeoverRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Alleen open verzoeken kunnen worden ingetrokken.',
            ]);
        }
        $shiftTakeoverRequest->update(['status' => ShiftTakeoverRequestStatus::Cancelled]);
        return back();
    }
}
