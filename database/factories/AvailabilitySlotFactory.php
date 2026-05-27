<?php

namespace Database\Factories;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilitySlot>
 */
class AvailabilitySlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->numberBetween(7, 16);

        return [
            'client_id' => Client::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'date' => null,
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:00', $startHour + fake()->numberBetween(1, 4)),
            'status' => AvailabilitySlotStatus::Open,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * A recurring slot with day_of_week set and no date.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => fake()->numberBetween(0, 6),
            'date' => null,
        ]);
    }

    /**
     * A one-time slot with date set and no day_of_week.
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => null,
            'date' => fake()->dateTimeBetween('now', '+3 months'),
        ]);
    }
}
