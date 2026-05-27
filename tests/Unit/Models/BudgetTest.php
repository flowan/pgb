<?php

namespace Tests\Unit\Models;

use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_category_belongs_to_client(): void
    {
        $client = Client::factory()->create();
        $category = BudgetCategory::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(Client::class, $category->client);
        $this->assertTrue($category->client->is($client));
    }

    public function test_budget_category_has_expenses(): void
    {
        $category = BudgetCategory::factory()->create();
        BudgetExpense::factory()->count(3)->create([
            'budget_category_id' => $category->id,
            'caregiver_id' => null,
        ]);

        $this->assertCount(3, $category->expenses);
        $this->assertInstanceOf(BudgetExpense::class, $category->expenses->first());
    }

    public function test_budget_category_recalculates_spent_amount(): void
    {
        $category = BudgetCategory::factory()->create(['spent_amount' => 0]);

        BudgetExpense::factory()->create([
            'budget_category_id' => $category->id,
            'amount' => 100.50,
            'caregiver_id' => null,
        ]);
        BudgetExpense::factory()->create([
            'budget_category_id' => $category->id,
            'amount' => 49.50,
            'caregiver_id' => null,
        ]);

        $category->updateSpentAmount();
        $category->refresh();

        $this->assertSame('150.00', $category->spent_amount);
    }

    public function test_budget_expense_belongs_to_category(): void
    {
        $category = BudgetCategory::factory()->create();
        $expense = BudgetExpense::factory()->create([
            'budget_category_id' => $category->id,
            'caregiver_id' => null,
        ]);

        $this->assertInstanceOf(BudgetCategory::class, $expense->budgetCategory);
        $this->assertTrue($expense->budgetCategory->is($category));
    }

    public function test_budget_expense_optionally_belongs_to_caregiver(): void
    {
        $expense = BudgetExpense::factory()->create(['caregiver_id' => null]);
        $this->assertNull($expense->caregiver);

        $caregiver = Caregiver::factory()->create();
        $expense->update(['caregiver_id' => $caregiver->id]);
        $expense->refresh();

        $this->assertInstanceOf(Caregiver::class, $expense->caregiver);
        $this->assertTrue($expense->caregiver->is($caregiver));
    }

    public function test_client_has_budget_categories(): void
    {
        $client = Client::factory()->create();
        BudgetCategory::factory()->count(2)->create(['client_id' => $client->id]);

        $this->assertCount(2, $client->budgetCategories);
        $this->assertInstanceOf(BudgetCategory::class, $client->budgetCategories->first());
    }
}
