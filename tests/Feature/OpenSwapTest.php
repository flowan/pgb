<?php

namespace Tests\Feature;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenSwapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function makeSetup(): array
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);

        $requesterUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $bidderUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $requester = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $requesterUser->id,
        ]);
        $bidder = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $bidderUser->id,
        ]);
        $requesterSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $requester->id,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $bidderSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $bidder->id,
            'start_time' => '13:00',
            'end_time' => '16:00',
        ]);

        return compact(
            'budgetHolder',
            'client',
            'requesterUser',
            'bidderUser',
            'requester',
            'bidder',
            'requesterSchedule',
            'bidderSchedule',
        );
    }

    public function test_caregiver_creates_open_swap_request(): void
    {
        $ctx = $this->makeSetup();

        $response = $this->actingAs($ctx['requesterUser'])->post(
            route('open-swap-requests.store'),
            [
                'schedule_id' => $ctx['requesterSchedule']->id,
                'date' => '2026-07-01',
                'notes' => 'Looking for a swap',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('open_swap_requests', [
            'schedule_id' => $ctx['requesterSchedule']->id,
            'requester_caregiver_id' => $ctx['requester']->id,
            'status' => OpenSwapRequestStatus::Open->value,
            'notes' => 'Looking for a swap',
        ]);
    }

    public function test_colleague_can_bid_and_requester_can_accept(): void
    {
        $ctx = $this->makeSetup();

        $openRequest = OpenSwapRequest::factory()->create([
            'schedule_id' => $ctx['requesterSchedule']->id,
            'schedule_exception_id' => null,
            'date' => '2026-07-01',
            'requester_caregiver_id' => $ctx['requester']->id,
            'status' => OpenSwapRequestStatus::Open,
        ]);

        $bidResponse = $this->actingAs($ctx['bidderUser'])->post(
            route('open-swap-requests.offers.store', $openRequest),
            [
                'offered_schedule_id' => $ctx['bidderSchedule']->id,
                'offered_date' => '2026-07-02',
            ]
        );

        $bidResponse->assertRedirect();
        $offer = OpenSwapOffer::firstOrFail();
        $this->assertEquals($openRequest->id, $offer->open_swap_request_id);
        $this->assertEquals($ctx['bidder']->id, $offer->offered_by_caregiver_id);
        $this->assertSame(OpenSwapOfferStatus::Pending, $offer->status);

        $acceptResponse = $this->actingAs($ctx['requesterUser'])->post(
            route('open-swap-requests.offers.accept', [$openRequest, $offer])
        );

        $acceptResponse->assertRedirect();

        $openRequest->refresh();
        $offer->refresh();
        $this->assertSame(OpenSwapRequestStatus::Fulfilled, $openRequest->status);
        $this->assertEquals($offer->id, $openRequest->selected_offer_id);
        $this->assertNotNull($openRequest->fulfilled_at);
        $this->assertCount(2, $openRequest->resulting_exception_ids);
        $this->assertSame(OpenSwapOfferStatus::Accepted, $offer->status);

        $firstId = $openRequest->resulting_exception_ids[0];
        $first = ScheduleException::findOrFail($firstId);
        $this->assertEquals($ctx['bidder']->id, $first->caregiver_id);
        $this->assertEquals($ctx['requesterSchedule']->id, $first->schedule_id);
        $this->assertSame(ScheduleExceptionType::Modified, $first->type);

        $secondId = $openRequest->resulting_exception_ids[1];
        $second = ScheduleException::findOrFail($secondId);
        $this->assertEquals($ctx['requester']->id, $second->caregiver_id);
        $this->assertEquals($ctx['bidderSchedule']->id, $second->schedule_id);
        $this->assertSame(ScheduleExceptionType::Modified, $second->type);
    }

    public function test_accepting_one_offer_declines_others(): void
    {
        $ctx = $this->makeSetup();

        $thirdUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $thirdCaregiver = Caregiver::factory()->create([
            'client_id' => $ctx['client']->id,
            'user_id' => $thirdUser->id,
        ]);
        $thirdSchedule = Schedule::factory()->create([
            'client_id' => $ctx['client']->id,
            'caregiver_id' => $thirdCaregiver->id,
            'start_time' => '17:00',
            'end_time' => '19:00',
        ]);

        $openRequest = OpenSwapRequest::factory()->create([
            'schedule_id' => $ctx['requesterSchedule']->id,
            'schedule_exception_id' => null,
            'date' => '2026-07-01',
            'requester_caregiver_id' => $ctx['requester']->id,
            'status' => OpenSwapRequestStatus::Open,
        ]);

        $offerA = OpenSwapOffer::factory()->create([
            'open_swap_request_id' => $openRequest->id,
            'offered_by_caregiver_id' => $ctx['bidder']->id,
            'offered_schedule_id' => $ctx['bidderSchedule']->id,
            'offered_schedule_exception_id' => null,
            'offered_date' => '2026-07-02',
            'status' => OpenSwapOfferStatus::Pending,
        ]);

        $offerB = OpenSwapOffer::factory()->create([
            'open_swap_request_id' => $openRequest->id,
            'offered_by_caregiver_id' => $thirdCaregiver->id,
            'offered_schedule_id' => $thirdSchedule->id,
            'offered_schedule_exception_id' => null,
            'offered_date' => '2026-07-03',
            'status' => OpenSwapOfferStatus::Pending,
        ]);

        $response = $this->actingAs($ctx['requesterUser'])->post(
            route('open-swap-requests.offers.accept', [$openRequest, $offerA])
        );

        $response->assertRedirect();

        $offerA->refresh();
        $offerB->refresh();
        $openRequest->refresh();

        $this->assertSame(OpenSwapOfferStatus::Accepted, $offerA->status);
        $this->assertSame(OpenSwapOfferStatus::Declined, $offerB->status);
        $this->assertSame(OpenSwapRequestStatus::Fulfilled, $openRequest->status);
        $this->assertEquals($offerA->id, $openRequest->selected_offer_id);
    }
}
