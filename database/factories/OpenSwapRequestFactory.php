<?php

namespace Database\Factories;

use App\Enums\OpenSwapRequestStatus;
use App\Models\Caregiver;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenSwapRequest>
 */
class OpenSwapRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'schedule_exception_id' => null,
            'date' => now()->addDays(7)->toDateString(),
            'requester_caregiver_id' => Caregiver::factory(),
            'status' => OpenSwapRequestStatus::Open,
            'selected_offer_id' => null,
            'fulfilled_at' => null,
            'resulting_exception_ids' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
