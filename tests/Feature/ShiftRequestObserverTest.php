<?php

namespace Tests\Feature;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\ShiftTakeoverRequestStatus;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;
use App\Models\ShiftTakeoverRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftRequestObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_schedule_cancels_open_takeover_offer(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'offered_by_caregiver_id' => $caregiver->id,
            'status' => ShiftTakeoverOfferStatus::Open,
        ]);

        $schedule->delete();

        $this->assertSame(ShiftTakeoverOfferStatus::Cancelled, $offer->fresh()->status);
    }

    public function test_deleting_schedule_cancels_pending_swap_request(): void
    {
        $client = Client::factory()->create();
        $requester = Caregiver::factory()->create(['client_id' => $client->id]);
        $target = Caregiver::factory()->create(['client_id' => $client->id]);

        $requesterSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $requester->id,
        ]);
        $targetSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $target->id,
        ]);

        $swap = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $requester->id,
            'requester_schedule_id' => $requesterSchedule->id,
            'target_caregiver_id' => $target->id,
            'target_schedule_id' => $targetSchedule->id,
            'status' => ShiftSwapRequestStatus::Pending,
        ]);

        $requesterSchedule->delete();

        $this->assertSame(ShiftSwapRequestStatus::Cancelled, $swap->fresh()->status);
    }

    public function test_deleting_schedule_cancels_open_swap_request(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $request = OpenSwapRequest::factory()->create([
            'schedule_id' => $schedule->id,
            'requester_caregiver_id' => $caregiver->id,
            'status' => OpenSwapRequestStatus::Open,
        ]);

        $schedule->delete();

        $this->assertSame(OpenSwapRequestStatus::Cancelled, $request->fresh()->status);
    }

    public function test_deleting_schedule_exception_cancels_open_takeover_offer(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);
        $exception = ScheduleException::factory()->create([
            'schedule_id' => $schedule->id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => null,
            'schedule_exception_id' => $exception->id,
            'offered_by_caregiver_id' => $caregiver->id,
            'status' => ShiftTakeoverOfferStatus::Open,
        ]);

        $exception->delete();

        $this->assertSame(ShiftTakeoverOfferStatus::Cancelled, $offer->fresh()->status);
    }

    public function test_deleting_schedule_cancels_pending_takeover_request(): void
    {
        $client = Client::factory()->create();
        $requester = Caregiver::factory()->create(['client_id' => $client->id]);
        $target = Caregiver::factory()->create(['client_id' => $client->id]);
        $targetSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $target->id,
        ]);

        $req = ShiftTakeoverRequest::factory()->create([
            'requester_caregiver_id' => $requester->id,
            'target_caregiver_id' => $target->id,
            'target_schedule_id' => $targetSchedule->id,
            'status' => ShiftTakeoverRequestStatus::Pending,
        ]);

        $targetSchedule->delete();

        $this->assertSame(ShiftTakeoverRequestStatus::Cancelled, $req->fresh()->status);
    }
}
