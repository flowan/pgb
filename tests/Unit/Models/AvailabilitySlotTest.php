<?php

namespace Tests\Unit\Models;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilitySlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_slot_belongs_to_client(): void
    {
        $client = Client::factory()->create();
        $slot = AvailabilitySlot::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(Client::class, $slot->client);
        $this->assertTrue($slot->client->is($client));
    }

    public function test_client_has_availability_slots(): void
    {
        $client = Client::factory()->create();
        AvailabilitySlot::factory()->count(3)->create(['client_id' => $client->id]);

        $this->assertCount(3, $client->availabilitySlots);
        $this->assertInstanceOf(AvailabilitySlot::class, $client->availabilitySlots->first());
    }

    public function test_recurring_slot_has_day_of_week_and_no_date(): void
    {
        $slot = AvailabilitySlot::factory()->recurring()->create();

        $this->assertNotNull($slot->day_of_week);
        $this->assertNull($slot->date);
    }

    public function test_one_time_slot_has_date_and_no_day_of_week(): void
    {
        $slot = AvailabilitySlot::factory()->oneTime()->create();

        $this->assertNull($slot->day_of_week);
        $this->assertNotNull($slot->date);
    }

    public function test_slot_defaults_to_open_status(): void
    {
        $slot = AvailabilitySlot::factory()->create();

        $this->assertSame(AvailabilitySlotStatus::Open, $slot->status);
    }

    public function test_claimed_slot_has_caregiver_and_timestamp(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $slot = AvailabilitySlot::factory()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
        ]);

        $this->assertSame(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertInstanceOf(Caregiver::class, $slot->claimedBy);
        $this->assertTrue($slot->claimedBy->is($caregiver));
        $this->assertNotNull($slot->claimed_at);
    }
}
