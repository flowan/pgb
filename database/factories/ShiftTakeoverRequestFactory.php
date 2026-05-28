<?php

namespace Database\Factories;

use App\Enums\ShiftTakeoverRequestStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ShiftTakeoverRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTakeoverRequest>
 */
class ShiftTakeoverRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requester_caregiver_id' => Caregiver::factory(),
            'target_caregiver_id' => Caregiver::factory(),
            'target_schedule_id' => Schedule::factory(),
            'target_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => ShiftTakeoverRequestStatus::Pending,
        ];
    }
}
