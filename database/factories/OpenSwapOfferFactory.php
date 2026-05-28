<?php

namespace Database\Factories;

use App\Enums\OpenSwapOfferStatus;
use App\Models\Caregiver;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenSwapOffer>
 */
class OpenSwapOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'open_swap_request_id' => OpenSwapRequest::factory(),
            'offered_by_caregiver_id' => Caregiver::factory(),
            'offered_schedule_id' => Schedule::factory(),
            'offered_schedule_exception_id' => null,
            'offered_date' => now()->addDays(10)->toDateString(),
            'status' => OpenSwapOfferStatus::Pending,
        ];
    }
}
