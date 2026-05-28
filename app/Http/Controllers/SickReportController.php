<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleExceptionType;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftTakeoverOffer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SickReportController extends Controller
{
    /**
     * Two modes:
     * - single: cancel one specific shift occurrence (schedule+date or exception)
     * - range: cancel all my upcoming shift occurrences between start/end date
     *
     * In both cases each cancelled occurrence:
     *   - gets a ScheduleException type=cancelled with due_to_sickness=true
     *   - gets a ShiftTakeoverOffer so colleagues can claim it
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if ($user->role !== UserRole::Caregiver) {
            abort(403);
        }

        $validated = $request->validate([
            'scope' => ['required', 'in:single,range'],
            // single
            'kind' => ['required_if:scope,single', 'in:schedule,exception'],
            'id' => ['required_if:scope,single', 'integer'],
            'date' => ['required_if:scope,single', 'date'],
            // range
            'start_date' => ['required_if:scope,range', 'date'],
            'end_date' => ['required_if:scope,range', 'date', 'after_or_equal:start_date'],
        ]);

        $myCaregiverIds = Caregiver::where('user_id', $user->id)->pluck('id')->all();
        if (empty($myCaregiverIds)) {
            abort(403);
        }

        $created = 0;
        DB::transaction(function () use ($validated, $myCaregiverIds, &$created) {
            if ($validated['scope'] === 'single') {
                $created = $this->reportSingle(
                    $validated['kind'],
                    (int) $validated['id'],
                    $validated['date'],
                    $myCaregiverIds,
                );
            } else {
                $created = $this->reportRange(
                    CarbonImmutable::parse($validated['start_date']),
                    CarbonImmutable::parse($validated['end_date']),
                    $myCaregiverIds,
                );
            }
        });

        if ($created === 0) {
            throw ValidationException::withMessages([
                'scope' => 'Geen shifts gevonden om als ziek te melden.',
            ]);
        }

        return back();
    }

    private function reportSingle(string $kind, int $id, string $date, array $myCaregiverIds): int
    {
        if ($kind === 'schedule') {
            $schedule = Schedule::findOrFail($id);
            if (! in_array($schedule->caregiver_id, $myCaregiverIds, true)) abort(403);
            $this->createSicknessFor($schedule, $date, $schedule->caregiver_id);
            return 1;
        }

        $exception = ScheduleException::findOrFail($id);
        if (! in_array($exception->caregiver_id, $myCaregiverIds, true)) abort(403);

        // For an exception we can't easily "cancel" it the same way — cancel by creating
        // a new cancellation that effectively supersedes; the offer points at the exception.
        $this->createSicknessFromException($exception);
        return 1;
    }

    private function reportRange(CarbonImmutable $start, CarbonImmutable $end, array $myCaregiverIds): int
    {
        $today = CarbonImmutable::today();
        if ($end->lessThan($today)) return 0;
        $start = $start->lessThan($today) ? $today : $start;

        // Skip dates already cancelled/modified for this schedule
        $count = 0;

        $schedules = Schedule::whereIn('caregiver_id', $myCaregiverIds)->get();
        foreach ($schedules as $schedule) {
            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($this->ownDayOfWeek($cursor) === $schedule->day_of_week) {
                    $dateStr = $cursor->format('Y-m-d');
                    $alreadyCancelled = ScheduleException::where('schedule_id', $schedule->id)
                        ->whereDate('date', $dateStr)
                        ->whereIn('type', [ScheduleExceptionType::Cancelled->value, ScheduleExceptionType::Modified->value])
                        ->exists();
                    if (! $alreadyCancelled) {
                        $this->createSicknessFor($schedule, $dateStr, $schedule->caregiver_id);
                        $count++;
                    }
                }
                $cursor = $cursor->addDay();
            }
        }

        // Also one-off exceptions in the range (type=added) that are mine
        $addedExceptions = ScheduleException::whereIn('caregiver_id', $myCaregiverIds)
            ->where('type', ScheduleExceptionType::Added)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();
        foreach ($addedExceptions as $ex) {
            $this->createSicknessFromException($ex);
            $count++;
        }

        return $count;
    }

    private function createSicknessFor(Schedule $schedule, string $date, int $caregiverId): void
    {
        $cancellation = ScheduleException::create([
            'schedule_id' => $schedule->id,
            'client_id' => $schedule->client_id,
            'caregiver_id' => $caregiverId,
            'date' => $date,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'type' => ScheduleExceptionType::Cancelled,
            'due_to_sickness' => true,
        ]);

        // Auto-create takeover offer for this shift occurrence
        ShiftTakeoverOffer::create([
            'schedule_id' => $schedule->id,
            'date' => $date,
            'offered_by_caregiver_id' => $caregiverId,
            'status' => ShiftTakeoverOfferStatus::Open,
            'notes' => 'Automatisch aangemaakt na ziekmelding',
        ]);
    }

    private function createSicknessFromException(ScheduleException $exception): void
    {
        // Cancel by deleting the original added exception and... actually a cleaner pattern is
        // to keep the original (so we know what it was) and add a cancellation that mirrors it.
        // But the WeekView would then show the original (added) AND the cancellation.
        // Simplest: mark the exception itself as sickness-cancelled by replacing its type.
        $exception->update([
            'type' => ScheduleExceptionType::Cancelled,
            'due_to_sickness' => true,
        ]);

        ShiftTakeoverOffer::create([
            'schedule_exception_id' => $exception->id,
            'date' => $exception->date->format('Y-m-d'),
            'offered_by_caregiver_id' => $exception->caregiver_id,
            'status' => ShiftTakeoverOfferStatus::Open,
            'notes' => 'Automatisch aangemaakt na ziekmelding',
        ]);
    }

    private function ownDayOfWeek(CarbonImmutable $d): int
    {
        // 1=Monday .. 7=Sunday → 0=Monday .. 6=Sunday
        return $d->dayOfWeekIso - 1;
    }
}
