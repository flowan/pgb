<?php

namespace Database\Factories;

use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\Caregiver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetExpense>
 */
class BudgetExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_category_id' => BudgetCategory::factory(),
            'caregiver_id' => fake()->optional()->passthrough(Caregiver::factory()),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'date' => fake()->date(),
        ];
    }
}
