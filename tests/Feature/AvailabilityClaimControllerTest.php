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

    private function setupCaregiver(): array
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);
        return [$caregiverUser, $caregiver, $client];
    }

    public function test_caregiver_can_claim_recurring_slot_forever(): void
    {
        [$caregiverUser, $caregiver, $client] = $this->setupCaregiver();
        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $response = $this->actingAs($caregiverUser)->post(
            route('availability-slots.claim', $slot),
            ['scope' => 'forever']
        );

        $response->assertRedirect(route('my-schedule'));
        $slot->refresh();
        $this->assertSame(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertEquals($caregiver->id, $slot->claimed_by);
        $this->assertNotNull($slot->schedule_id);
        $this->assertDatabaseHas('schedules', [
            'id' => $slot->schedule_id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'day_of_week' => 2,
        ]);
    }

    public function test_caregiver_can_claim_recurring_slot_once(): void
    {
        [$caregiverUser, $caregiver, $client] = $this->setupCaregiver();
        // day_of_week 2 = Wednesday in our convention (0=Mon)
        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $response = $this->actingAs($caregiverUser)->post(
            route('availability-slots.claim', $slot),
            ['scope' => 'once', 'date' => '2026-06-03'] // Wed 3 June 2026
        );

        $response->assertRedirect(route('my-schedule'));
        $slot->refresh();
        $this->assertSame(AvailabilitySlotStatus::Open, $slot->status, 'Slot stays open after once claim');
        $this->assertNull($slot->schedule_id);
        $this->assertEquals(['2026-06-03'], $slot->claimed_dates);
        $this->assertDatabaseCount('schedule_exceptions', 1);
        $exception = \App\Models\ScheduleException::first();
        $this->assertEquals($caregiver->id, $exception->caregiver_id);
        $this->assertEquals('2026-06-03', $exception->date->format('Y-m-d'));
        $this->assertSame(ScheduleExceptionType::Added, $exception->type);
    }

    public function test_caregiver_can_claim_recurring_slot_until_date(): void
    {
        [$caregiverUser, $caregiver, $client] = $this->setupCaregiver();
        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'day_of_week' => 2, // Wednesday
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $response = $this->actingAs($caregiverUser)->post(
            route('availability-slots.claim', $slot),
            ['scope' => 'until', 'date' => '2026-06-03', 'until_date' => '2026-06-24']
        );

        $response->assertRedirect(route('my-schedule'));
        $slot->refresh();
        // 3, 10, 17, 24 June 2026 are Wednesdays
        $this->assertCount(4, $slot->claimed_dates);
        $this->assertSame(AvailabilitySlotStatus::Open, $slot->status);
        $this->assertDatabaseCount('schedule_exceptions', 4);
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
