<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use Illuminate\Http\RedirectResponse;

class AvailabilityReleaseController extends Controller
{
    /**
     * Caregiver gives back a shift they previously claimed "forever".
     * Deletes the resulting Schedule; the ScheduleObserver resets the slot to open.
     */
    public function store(AvailabilitySlot $availabilitySlot): RedirectResponse
    {
        $user = auth()->user();

        $caregiver = Caregiver::where('user_id', $user->id)
            ->where('client_id', $availabilitySlot->client_id)
            ->first();

        if (! $caregiver || $availabilitySlot->claimed_by !== $caregiver->id) {
            abort(403);
        }

        // Only "forever" claims have a resulting Schedule
        if ($availabilitySlot->schedule_id) {
            $availabilitySlot->schedule()->delete();
            // ScheduleObserver resets slot to open with claimed_by/at/schedule_id null
        }

        return redirect()->route('my-schedule');
    }
}
