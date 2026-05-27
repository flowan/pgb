<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_belongs_to_budget_holder(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);

        $this->assertInstanceOf(User::class, $client->budgetHolder);
        $this->assertTrue($client->budgetHolder->is($budgetHolder));
    }

    public function test_budget_holder_has_clients(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $clients = Client::factory()->count(3)->create(['budget_holder_id' => $budgetHolder->id]);

        $this->assertCount(3, $budgetHolder->clients);
        $this->assertTrue($budgetHolder->clients->first()->is($clients->first()));
    }
}
