<?php

namespace Tests\Feature;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilitySlotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_budget_holder_can_create_recurring_availability_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('clients.availability-slots.store', $client),
            [
                'type' => 'recurring',
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '12:00',
                'notes' => 'Weekly slot',
            ]
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseHas('availability_slots', [
            'client_id' => $client->id,
            'day_of_week' => 1,
            'date' => null,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'status' => AvailabilitySlotStatus::Open->value,
        ]);
    }

    public function test_budget_holder_can_create_one_time_availability_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('clients.availability-slots.store', $client),
            [
                'type' => 'one_time',
                'date' => '2026-07-01',
                'start_time' => '14:00',
                'end_time' => '16:00',
            ]
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseHas('availability_slots', [
            'client_id' => $client->id,
            'day_of_week' => null,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => AvailabilitySlotStatus::Open->value,
        ]);
    }

    public function test_budget_holder_can_delete_open_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $slot = AvailabilitySlot::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->delete(
            route('clients.availability-slots.destroy', [$client, $slot])
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseMissing('availability_slots', ['id' => $slot->id]);
    }

    public function test_deleting_claimed_slot_also_deletes_linked_schedule(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $slot = AvailabilitySlot::factory()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
            'schedule_id' => $schedule->id,
        ]);

        $response = $this->actingAs($user)->delete(
            route('clients.availability-slots.destroy', [$client, $slot])
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseMissing('availability_slots', ['id' => $slot->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_other_budget_holder_cannot_create_slot(): void
    {
        $owner = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $other = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $owner->id]);

        $response = $this->actingAs($other)->post(
            route('clients.availability-slots.store', $client),
            [
                'type' => 'recurring',
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '12:00',
            ]
        );

        $response->assertForbidden();
    }

    public function test_caregiver_cannot_create_slot(): void
    {
        $caregiver = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();

        $response = $this->actingAs($caregiver)->post(
            route('clients.availability-slots.store', $client),
            [
                'type' => 'recurring',
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '12:00',
            ]
        );

        $response->assertForbidden();
    }
}
