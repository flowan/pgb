<?php

namespace Database\Factories;

use App\Enums\ScheduleExceptionType;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleException>
 */
class ScheduleExceptionFactory extends Factory
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
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $startHour = fake()->numberBetween(7, 16);

        return [
            'schedule_id' => $schedule->id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'date' => fake()->dateTimeBetween('now', '+3 months'),
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:00', $startHour + fake()->numberBetween(1, 4)),
            'type' => fake()->randomElement(ScheduleExceptionType::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
