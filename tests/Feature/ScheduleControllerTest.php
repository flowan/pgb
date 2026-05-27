<?php

namespace Tests\Feature;

use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_shows_schedule_for_client(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $response = $this->actingAs($user)->get(route('clients.schedule.index', $client));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('schedule/index')
            ->has('schedules', 1)
            ->has('exceptions')
            ->has('caregivers')
            ->has('client')
        );
    }

    public function test_can_create_recurring_schedule(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->post(
            route('clients.schedules.store', $client),
            [
                'caregiver_id' => $caregiver->id,
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '12:00',
                'notes' => 'Ochtend sessie',
            ]
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseHas('schedules', [
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_can_delete_schedule(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $response = $this->actingAs($user)->delete(
            route('clients.schedules.destroy', [$client, $schedule])
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_can_add_schedule_exception(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $response = $this->actingAs($user)->post(
            route('clients.schedule-exceptions.store', $client),
            [
                'schedule_id' => $schedule->id,
                'caregiver_id' => $caregiver->id,
                'date' => '2026-06-01',
                'start_time' => '10:00',
                'end_time' => '13:00',
                'type' => 'modified',
                'notes' => 'Aangepaste tijd',
            ]
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseHas('schedule_exceptions', [
            'client_id' => $client->id,
            'schedule_id' => $schedule->id,
            'type' => ScheduleExceptionType::Modified->value,
        ]);
    }

    public function test_can_add_standalone_appointment(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->post(
            route('clients.schedule-exceptions.store', $client),
            [
                'schedule_id' => null,
                'caregiver_id' => $caregiver->id,
                'date' => '2026-06-15',
                'start_time' => '14:00',
                'end_time' => '16:00',
                'type' => 'added',
                'notes' => 'Extra afspraak',
            ]
        );

        $response->assertRedirect(route('clients.schedule.index', $client));
        $this->assertDatabaseHas('schedule_exceptions', [
            'client_id' => $client->id,
            'schedule_id' => null,
            'type' => ScheduleExceptionType::Added->value,
        ]);
    }
}
