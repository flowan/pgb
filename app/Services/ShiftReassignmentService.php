<?php

namespace App\Services;

use App\Enums\ScheduleExceptionType;
use App\Models\Schedule;
use App\Models\ScheduleException;

class ShiftReassignmentService
{
    public function reassign(
        Schedule|ScheduleException $source,
        string $date,
        int $newCaregiverId,
    ): ScheduleException {
        if ($source instanceof Schedule) {
            return ScheduleException::create([
                'schedule_id' => $source->id,
                'client_id' => $source->client_id,
                'caregiver_id' => $newCaregiverId,
                'date' => $date,
                'start_time' => $source->start_time,
                'end_time' => $source->end_time,
                'type' => ScheduleExceptionType::Modified,
            ]);
        }

        return ScheduleException::create([
            'schedule_id' => $source->schedule_id,
            'client_id' => $source->client_id,
            'caregiver_id' => $newCaregiverId,
            'date' => $date,
            'start_time' => $source->start_time,
            'end_time' => $source->end_time,
            'type' => ScheduleExceptionType::Modified,
        ]);
    }
}
