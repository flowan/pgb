<?php

namespace Database\Factories;

use App\Enums\CaregiverType;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caregiver>
 */
class CaregiverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'type' => fake()->randomElement(CaregiverType::cases()),
            'hourly_rate' => fake()->optional()->randomFloat(2, 15, 75),
        ];
    }
}
