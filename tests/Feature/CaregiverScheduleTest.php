<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_caregiver_can_view_their_own_schedule(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);
        Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $response = $this->actingAs($caregiverUser)->get(route('my-schedule'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('caregiver-schedule/index')
            ->has('clients', 1)
            ->where('clients.0.name', $client->name)
        );
    }

    public function test_caregiver_only_sees_own_schedules(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $otherCaregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);

        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);

        $ownCaregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);
        $otherCaregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $otherCaregiverUser->id,
        ]);

        Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $ownCaregiver->id,
            'day_of_week' => 1,
        ]);
        Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $otherCaregiver->id,
            'day_of_week' => 2,
        ]);

        $response = $this->actingAs($caregiverUser)->get(route('my-schedule'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('caregiver-schedule/index')
            ->has('clients', 1)
            ->has('clients.0.schedules', 1)
        );
    }

    public function test_budget_holder_cannot_access_caregiver_schedule(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);

        $response = $this->actingAs($user)->get(route('my-schedule'));

        $response->assertForbidden();
    }
}
