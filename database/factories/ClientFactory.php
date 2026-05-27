<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_holder_id' => User::factory(['role' => UserRole::BudgetHolder]),
            'name' => fake()->name(),
            'date_of_birth' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
