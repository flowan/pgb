<?php

namespace Tests\Feature;

use App\Enums\ScheduleExceptionType;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ShiftTakeoverOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTakeoverOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_caregiver_can_offer_own_shift(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $response = $this->actingAs($caregiverUser)->post(
            route('shift-takeover-offers.store'),
            [
                'schedule_id' => $schedule->id,
                'date' => '2026-07-01',
                'notes' => 'Going away',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_takeover_offers', [
            'schedule_id' => $schedule->id,
            'offered_by_caregiver_id' => $caregiver->id,
            'status' => ShiftTakeoverOfferStatus::Open->value,
            'notes' => 'Going away',
        ]);
        $this->assertSame('2026-07-01', ShiftTakeoverOffer::first()->date->toDateString());
    }

    public function test_colleague_can_claim_offer_and_creates_exception(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $offererUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $claimerUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $offerer = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $offererUser->id,
        ]);
        $claimer = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $claimerUser->id,
        ]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $offerer->id,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'schedule_exception_id' => null,
            'date' => '2026-07-15',
            'offered_by_caregiver_id' => $offerer->id,
            'status' => ShiftTakeoverOfferStatus::Open,
        ]);

        $response = $this->actingAs($claimerUser)->post(
            route('shift-takeover-offers.claim', $offer)
        );

        $response->assertRedirect();

        $offer->refresh();
        $this->assertSame(ShiftTakeoverOfferStatus::Claimed, $offer->status);
        $this->assertEquals($claimer->id, $offer->claimed_by_caregiver_id);
        $this->assertNotNull($offer->claimed_at);
        $this->assertNotNull($offer->resulting_exception_id);

        $this->assertDatabaseHas('schedule_exceptions', [
            'id' => $offer->resulting_exception_id,
            'schedule_id' => $schedule->id,
            'client_id' => $client->id,
            'caregiver_id' => $claimer->id,
            'type' => ScheduleExceptionType::Modified->value,
        ]);
        $this->assertSame('2026-07-15', \App\Models\ScheduleException::find($offer->resulting_exception_id)->date->toDateString());
    }

    public function test_offerer_cannot_claim_own_offer(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $offererUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $offerer = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $offererUser->id,
        ]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $offerer->id,
        ]);
        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'schedule_exception_id' => null,
            'date' => '2026-07-15',
            'offered_by_caregiver_id' => $offerer->id,
            'status' => ShiftTakeoverOfferStatus::Open,
        ]);

        $response = $this->actingAs($offererUser)->post(
            route('shift-takeover-offers.claim', $offer)
        );

        $response->assertForbidden();
    }

    public function test_offerer_can_cancel_own_offer(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $offererUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $offerer = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $offererUser->id,
        ]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $offerer->id,
        ]);
        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'schedule_exception_id' => null,
            'date' => '2026-07-15',
            'offered_by_caregiver_id' => $offerer->id,
            'status' => ShiftTakeoverOfferStatus::Open,
        ]);

        $response = $this->actingAs($offererUser)->delete(
            route('shift-takeover-offers.destroy', $offer)
        );

        $response->assertRedirect();
        $offer->refresh();
        $this->assertSame(ShiftTakeoverOfferStatus::Cancelled, $offer->status);
    }

    public function test_cannot_claim_already_claimed_offer(): void
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $offererUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $claimerUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $offerer = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $offererUser->id,
        ]);
        $claimer = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $claimerUser->id,
        ]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $offerer->id,
        ]);
        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'schedule_exception_id' => null,
            'date' => '2026-07-15',
            'offered_by_caregiver_id' => $offerer->id,
            'status' => ShiftTakeoverOfferStatus::Claimed,
            'claimed_by_caregiver_id' => $claimer->id,
            'claimed_at' => now(),
        ]);

        $response = $this->actingAs($claimerUser)
            ->from('/dashboard')
            ->post(route('shift-takeover-offers.claim', $offer));

        $response->assertSessionHasErrors('status');
    }
}
