<?php

namespace Database\Factories;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTakeoverOffer>
 */
class ShiftTakeoverOfferFactory extends Factory
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
            'offered_by_caregiver_id' => Caregiver::factory(),
            'status' => ShiftTakeoverOfferStatus::Open,
            'claimed_by_caregiver_id' => null,
            'claimed_at' => null,
            'resulting_exception_id' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
