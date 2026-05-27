<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_shows_clients_index_for_budget_holder(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('clients/index')
            ->has('clients', 1)
            ->where('clients.0.name', $client->name)
        );
    }

    public function test_only_shows_own_clients(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $otherUser = User::factory()->create(['role' => UserRole::BudgetHolder]);

        Client::factory()->create(['budget_holder_id' => $user->id]);
        Client::factory()->create(['budget_holder_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('clients/index')
            ->has('clients', 1)
        );
    }

    public function test_can_create_client(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'date_of_birth' => '2020-01-15',
            'notes' => 'Some notes',
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'budget_holder_id' => $user->id,
        ]);
    }

    public function test_can_update_client(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('clients.update', $client), [
            'name' => 'Updated Name',
            'date_of_birth' => '2019-06-01',
            'notes' => null,
        ]);

        $response->assertRedirect(route('clients.show', $client));
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_client(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('clients.destroy', $client));

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_shows_client_detail(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('clients.show', $client));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('clients/show')
            ->where('client.name', $client->name)
        );
    }
}
