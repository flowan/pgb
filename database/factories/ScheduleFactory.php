<?php

namespace Database\Factories;

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $startHour = fake()->numberBetween(7, 16);

        return [
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:00', $startHour + fake()->numberBetween(1, 4)),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
