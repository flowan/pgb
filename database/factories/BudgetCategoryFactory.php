<?php

namespace Database\Factories;

use App\Models\BudgetCategory;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetCategory>
 */
class BudgetCategoryFactory extends Factory
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
            'name' => fake()->randomElement(['Begeleiding individueel', 'Persoonlijke verzorging', 'Verpleging', 'Dagbesteding', 'Kortdurend verblijf']),
            'allocated_amount' => fake()->randomFloat(2, 500, 10000),
            'spent_amount' => 0,
        ];
    }
}
