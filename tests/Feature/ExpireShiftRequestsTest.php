<?php

namespace Tests\Feature;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\ShiftTakeoverRequestStatus;
use App\Models\ShiftTakeoverOffer;
use App\Models\ShiftTakeoverRequest;
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

    public function test_command_expires_past_takeover_requests(): void
    {
        $past = ShiftTakeoverRequest::factory()->create([
            'status' => ShiftTakeoverRequestStatus::Pending,
            'target_date' => '2020-01-01',
        ]);
        $future = ShiftTakeoverRequest::factory()->create([
            'status' => ShiftTakeoverRequestStatus::Pending,
            'target_date' => '2099-12-31',
        ]);

        $this->artisan('shift-requests:expire')->assertSuccessful();

        $this->assertEquals(ShiftTakeoverRequestStatus::Expired, $past->fresh()->status);
        $this->assertEquals(ShiftTakeoverRequestStatus::Pending, $future->fresh()->status);
    }
}
