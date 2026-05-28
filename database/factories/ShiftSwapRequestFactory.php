<?php

namespace Database\Factories;

use App\Enums\ShiftSwapRequestStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ShiftSwapRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftSwapRequest>
 */
class ShiftSwapRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_caregiver_id' => Caregiver::factory(),
            'requester_schedule_id' => Schedule::factory(),
            'requester_schedule_exception_id' => null,
            'requester_date' => now()->addDays(7)->toDateString(),
            'target_caregiver_id' => Caregiver::factory(),
            'target_schedule_id' => Schedule::factory(),
            'target_schedule_exception_id' => null,
            'target_date' => now()->addDays(10)->toDateString(),
            'status' => ShiftSwapRequestStatus::Pending,
            'responded_at' => null,
            'decline_reason' => null,
            'resulting_exception_ids' => null,
        ];
    }
}
