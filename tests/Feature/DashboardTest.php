<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_shows_dashboard_for_budget_holder_with_client_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('clients', 1)
            ->where('clients.0.name', $client->name)
        );
    }

    public function test_redirects_caregiver_to_their_schedule_view(): void
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect('/my-schedule');
    }
}
