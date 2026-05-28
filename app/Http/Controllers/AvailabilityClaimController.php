<?php

namespace App\Http\Controllers;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AvailabilityClaimController extends Controller
{
    public function store(Request $request, AvailabilitySlot $availabilitySlot): RedirectResponse
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

        // One-time slot: simple claim, scope is irrelevant
        if ($availabilitySlot->date !== null) {
            $exception = ScheduleException::create([
                'client_id' => $availabilitySlot->client_id,
                'caregiver_id' => $caregiver->id,
                'date' => $availabilitySlot->date,
                'start_time' => $availabilitySlot->start_time,
                'end_time' => $availabilitySlot->end_time,
                'type' => ScheduleExceptionType::Added,
            ]);

            $availabilitySlot->update([
                'status' => AvailabilitySlotStatus::Claimed,
                'claimed_by' => $caregiver->id,
                'claimed_at' => now(),
                'schedule_exception_id' => $exception->id,
            ]);

            return redirect()->route('my-schedule');
        }

        // Recurring slot: requires scope
        $validated = $request->validate([
            'scope' => ['required', 'in:once,until,forever'],
            'date' => ['required_unless:scope,forever', 'date'],
            'until_date' => ['required_if:scope,until', 'date', 'after_or_equal:date'],
        ]);

        $scope = $validated['scope'];

        if ($scope === 'forever') {
            $schedule = Schedule::create([
                'client_id' => $availabilitySlot->client_id,
                'caregiver_id' => $caregiver->id,
                'day_of_week' => $availabilitySlot->day_of_week,
                'start_time' => $availabilitySlot->start_time,
                'end_time' => $availabilitySlot->end_time,
            ]);

            $availabilitySlot->update([
                'status' => AvailabilitySlotStatus::Claimed,
                'claimed_by' => $caregiver->id,
                'claimed_at' => now(),
                'schedule_id' => $schedule->id,
            ]);

            return redirect()->route('my-schedule');
        }

        // 'once' or 'until' — generate one or more ScheduleExceptions, keep slot open
        $startDate = CarbonImmutable::parse($validated['date']);
        $endDate = $scope === 'until'
            ? CarbonImmutable::parse($validated['until_date'])
            : $startDate;

        $claimedDates = $availabilitySlot->claimed_dates ?? [];
        $dates = $this->occurrencesBetween($startDate, $endDate, $availabilitySlot->day_of_week);

        if (empty($dates)) {
            throw ValidationException::withMessages([
                'until_date' => 'Geen occurrence van die dag in dit datumbereik.',
            ]);
        }

        foreach ($dates as $date) {
            $dateStr = $date->format('Y-m-d');
            if (in_array($dateStr, $claimedDates, true)) {
                continue; // already claimed by someone else for this date
            }

            ScheduleException::create([
                'client_id' => $availabilitySlot->client_id,
                'caregiver_id' => $caregiver->id,
                'date' => $dateStr,
                'start_time' => $availabilitySlot->start_time,
                'end_time' => $availabilitySlot->end_time,
                'type' => ScheduleExceptionType::Added,
            ]);
            $claimedDates[] = $dateStr;
        }

        $availabilitySlot->update([
            'claimed_dates' => $claimedDates,
        ]);

        return redirect()->route('my-schedule');
    }

    /**
     * Return all dates between start and end (inclusive) that fall on the given day_of_week.
     * day_of_week uses 0=Monday convention.
     */
    private function occurrencesBetween(CarbonImmutable $start, CarbonImmutable $end, int $dayOfWeek): array
    {
        // Convert our 0=Monday to Carbon's 1=Monday, ..., 0=Sunday convention
        $carbonDow = $dayOfWeek === 6 ? 0 : $dayOfWeek + 1;

        $dates = [];
        $cursor = $start;
        while ($cursor->lessThanOrEqualTo($end)) {
            if ($cursor->dayOfWeek === $carbonDow) {
                $dates[] = $cursor;
            }
            $cursor = $cursor->addDay();
        }
        return $dates;
    }
}
