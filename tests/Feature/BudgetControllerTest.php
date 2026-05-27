<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_shows_budget_overview_for_client(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $category = BudgetCategory::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->get(route('clients.budget.index', $client));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('budget/index')
            ->has('categories', 1)
            ->where('categories.0.name', $category->name)
            ->has('client')
            ->has('caregivers')
        );
    }

    public function test_can_create_budget_category(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('clients.budget-categories.store', $client),
            [
                'name' => 'Begeleiding',
                'allocated_amount' => '5000.00',
            ]
        );

        $response->assertRedirect(route('clients.budget.index', $client));
        $this->assertDatabaseHas('budget_categories', [
            'client_id' => $client->id,
            'name' => 'Begeleiding',
        ]);
    }

    public function test_can_add_expense(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $category = BudgetCategory::factory()->create([
            'client_id' => $client->id,
            'allocated_amount' => 5000,
            'spent_amount' => 0,
        ]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->post(
            route('clients.budget-expenses.store', $client),
            [
                'budget_category_id' => $category->id,
                'caregiver_id' => $caregiver->id,
                'description' => 'Sessie begeleiding',
                'amount' => '150.00',
                'date' => '2026-05-20',
            ]
        );

        $response->assertRedirect(route('clients.budget.index', $client));
        $this->assertDatabaseHas('budget_expenses', [
            'budget_category_id' => $category->id,
            'description' => 'Sessie begeleiding',
        ]);

        $category->refresh();
        $this->assertEquals('150.00', $category->spent_amount);
    }

    public function test_can_delete_expense_and_recalculates_spent_amount(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $category = BudgetCategory::factory()->create([
            'client_id' => $client->id,
            'allocated_amount' => 5000,
            'spent_amount' => 250,
        ]);
        $expense1 = BudgetExpense::factory()->create([
            'budget_category_id' => $category->id,
            'amount' => 100,
        ]);
        $expense2 = BudgetExpense::factory()->create([
            'budget_category_id' => $category->id,
            'amount' => 150,
        ]);

        $response = $this->actingAs($user)->delete(
            route('clients.budget-expenses.destroy', [$client, $expense1])
        );

        $response->assertRedirect(route('clients.budget.index', $client));
        $this->assertDatabaseMissing('budget_expenses', ['id' => $expense1->id]);

        $category->refresh();
        $this->assertEquals('150.00', $category->spent_amount);
    }
}
