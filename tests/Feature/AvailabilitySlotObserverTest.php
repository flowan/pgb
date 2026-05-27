<?php

namespace Tests\Feature;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilitySlotObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_schedule_resets_linked_availability_slot(): void
    {
        $client = Client::factory()->create();
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

        $schedule->delete();

        $slot->refresh();
        $this->assertSame(AvailabilitySlotStatus::Open, $slot->status);
        $this->assertNull($slot->claimed_by);
        $this->assertNull($slot->claimed_at);
        $this->assertNull($slot->schedule_id);
    }

    public function test_deleting_schedule_exception_resets_linked_availability_slot(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $exception = ScheduleException::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'schedule_id' => null,
        ]);

        $slot = AvailabilitySlot::factory()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
            'schedule_exception_id' => $exception->id,
        ]);

        $exception->delete();

        $slot->refresh();
        $this->assertSame(AvailabilitySlotStatus::Open, $slot->status);
        $this->assertNull($slot->claimed_by);
        $this->assertNull($slot->claimed_at);
        $this->assertNull($slot->schedule_exception_id);
    }

    public function test_deleting_unlinked_schedule_does_not_affect_slots(): void
    {
        $client = Client::factory()->create();
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
            'schedule_id' => null,
        ]);

        $schedule->delete();

        $slot->refresh();
        $this->assertSame(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertEquals($caregiver->id, $slot->claimed_by);
        $this->assertNotNull($slot->claimed_at);
    }
}
