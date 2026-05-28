<?php

namespace Tests\Feature;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireShiftRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_past_takeover_offers(): void
    {
        $past = ShiftTakeoverOffer::factory()->create([
            'status' => ShiftTakeoverOfferStatus::Open,
            'date' => '2020-01-01',
        ]);
        $future = ShiftTakeoverOffer::factory()->create([
            'status' => ShiftTakeoverOfferStatus::Open,
            'date' => '2099-12-31',
        ]);

        $this->artisan('shift-requests:expire')->assertSuccessful();

        $this->assertEquals(ShiftTakeoverOfferStatus::Expired, $past->fresh()->status);
        $this->assertEquals(ShiftTakeoverOfferStatus::Open, $future->fresh()->status);
    }
}
