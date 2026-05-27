<?php

namespace Tests\Feature;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityClaimControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_claim_open_recurring_slot(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);

        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $response = $this->actingAs($caregiverUser)->post(
            route('availability-slots.claim', $slot)
        );

        $response->assertRedirect(route('my-schedule'));

        $slot->refresh();
        $this->assertSame(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertEquals($caregiver->id, $slot->claimed_by);
        $this->assertNotNull($slot->claimed_at);
        $this->assertNotNull($slot->schedule_id);

        $this->assertDatabaseHas('schedules', [
            'id' => $slot->schedule_id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'day_of_week' => 2,
        ]);
    }

    public function test_caregiver_can_claim_open_one_time_slot(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);

        $slot = AvailabilitySlot::factory()->oneTime()->create([
            'client_id' => $client->id,
            'date' => '2026-07-15',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response = $this->actingAs($caregiverUser)->post(
            route('availability-slots.claim', $slot)
        );

        $response->assertRedirect(route('my-schedule'));

        $slot->refresh();
        $this->assertSame(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertEquals($caregiver->id, $slot->claimed_by);
        $this->assertNotNull($slot->claimed_at);
        $this->assertNotNull($slot->schedule_exception_id);

        $this->assertDatabaseHas('schedule_exceptions', [
            'id' => $slot->schedule_exception_id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'type' => ScheduleExceptionType::Added->value,
        ]);
    }

    public function test_caregiver_cannot_claim_already_claimed_slot(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);

        $slot = AvailabilitySlot::factory()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
        ]);

        $response = $this->actingAs($caregiverUser)->post(
            route('availability-slots.claim', $slot)
        );

        $response->assertStatus(409);
    }

    public function test_caregiver_not_linked_to_client_cannot_claim(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        // Caregiver linked to a different client
        Caregiver::factory()->create(['user_id' => $caregiverUser->id]);

        $slot = AvailabilitySlot::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($caregiverUser)->post(
            route('availability-slots.claim', $slot)
        );

        $response->assertForbidden();
    }

    public function test_budget_holder_cannot_claim_slot(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $slot = AvailabilitySlot::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($budgetHolder)->post(
            route('availability-slots.claim', $slot)
        );

        $response->assertForbidden();
    }
}
