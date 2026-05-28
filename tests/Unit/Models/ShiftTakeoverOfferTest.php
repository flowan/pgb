<?php

namespace Tests\Unit\Models;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ShiftTakeoverOffer;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTakeoverOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_belongs_to_schedule_and_offerer(): void
    {
        $schedule = Schedule::factory()->create();
        $caregiver = Caregiver::factory()->create();

        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'offered_by_caregiver_id' => $caregiver->id,
        ]);

        $this->assertInstanceOf(Schedule::class, $offer->schedule);
        $this->assertTrue($offer->schedule->is($schedule));
        $this->assertInstanceOf(Caregiver::class, $offer->offeredBy);
        $this->assertTrue($offer->offeredBy->is($caregiver));
    }

    public function test_offer_defaults_to_open_status(): void
    {
        $offer = ShiftTakeoverOffer::factory()->create();

        $this->assertSame(ShiftTakeoverOfferStatus::Open, $offer->status);
    }

    public function test_offer_casts_date_and_status(): void
    {
        $offer = ShiftTakeoverOffer::factory()->create([
            'date' => '2026-06-15',
            'status' => ShiftTakeoverOfferStatus::Claimed,
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $offer->date);
        $this->assertSame('2026-06-15', $offer->date->toDateString());
        $this->assertInstanceOf(ShiftTakeoverOfferStatus::class, $offer->status);
        $this->assertSame(ShiftTakeoverOfferStatus::Claimed, $offer->status);
    }
}
