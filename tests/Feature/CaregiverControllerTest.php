<?php

namespace Tests\Feature;

use App\Enums\CaregiverType;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_shows_caregivers_for_client(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->get(route('clients.caregivers.index', $client));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('caregivers/index')
            ->has('caregivers', 1)
            ->where('caregivers.0.name', $caregiver->name)
        );
    }

    public function test_can_create_caregiver(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('clients.caregivers.store', $client),
            [
                'name' => 'Test Caregiver',
                'type' => 'care_worker',
                'hourly_rate' => '25.50',
            ]
        );

        $response->assertRedirect(route('clients.caregivers.index', $client));
        $this->assertDatabaseHas('caregivers', [
            'client_id' => $client->id,
            'name' => 'Test Caregiver',
            'type' => CaregiverType::CareWorker->value,
        ]);
    }

    public function test_can_update_caregiver(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->put(
            route('clients.caregivers.update', [$client, $caregiver]),
            [
                'name' => 'Updated Caregiver',
                'type' => 'zzp',
                'hourly_rate' => '40.00',
            ]
        );

        $response->assertRedirect(route('clients.caregivers.index', $client));
        $this->assertDatabaseHas('caregivers', [
            'id' => $caregiver->id,
            'name' => 'Updated Caregiver',
            'type' => CaregiverType::Zzp->value,
        ]);
    }

    public function test_can_delete_caregiver(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->delete(
            route('clients.caregivers.destroy', [$client, $caregiver])
        );

        $response->assertRedirect(route('clients.caregivers.index', $client));
        $this->assertDatabaseMissing('caregivers', ['id' => $caregiver->id]);
    }

    public function test_prevents_access_to_other_holders_client_caregivers(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $otherUser = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $otherClient = Client::factory()->create(['budget_holder_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get(
            route('clients.caregivers.index', $otherClient)
        );

        $response->assertForbidden();
    }
}
