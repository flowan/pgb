<?php

namespace Tests\Feature;

use App\Enums\ScheduleExceptionType;
use App\Enums\ShiftTakeoverRequestStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftTakeoverRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTakeoverRequestTest extends TestCase
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
        $targetUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $requester = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $requesterUser->id,
        ]);
        $target = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $targetUser->id,
        ]);
        $targetSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $target->id,
            'start_time' => '13:00',
            'end_time' => '16:00',
        ]);

        return compact(
            'budgetHolder',
            'client',
            'requesterUser',
            'targetUser',
            'requester',
            'target',
            'targetSchedule',
        );
    }

    public function test_caregiver_creates_takeover_request(): void
    {
        $ctx = $this->makeSetup();

        $response = $this->actingAs($ctx['requesterUser'])->post(
            route('shift-takeover-requests.store'),
            [
                'target_caregiver_id' => $ctx['target']->id,
                'target_schedule_id' => $ctx['targetSchedule']->id,
                'target_date' => '2026-07-02',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_takeover_requests', [
            'requester_caregiver_id' => $ctx['requester']->id,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'status' => ShiftTakeoverRequestStatus::Pending->value,
        ]);
    }

    public function test_target_can_accept_and_creates_exception(): void
    {
        $ctx = $this->makeSetup();

        $req = ShiftTakeoverRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'target_date' => '2026-07-02',
            'status' => ShiftTakeoverRequestStatus::Pending,
        ]);

        $response = $this->actingAs($ctx['targetUser'])->post(
            route('shift-takeover-requests.accept', $req)
        );

        $response->assertRedirect();
        $req->refresh();
        $this->assertSame(ShiftTakeoverRequestStatus::Accepted, $req->status);
        $this->assertNotNull($req->responded_at);
        $this->assertNotNull($req->resulting_exception_id);

        $exception = ScheduleException::findOrFail($req->resulting_exception_id);
        $this->assertEquals($ctx['requester']->id, $exception->caregiver_id);
        $this->assertEquals($ctx['targetSchedule']->id, $exception->schedule_id);
        $this->assertSame(ScheduleExceptionType::Modified, $exception->type);
        $this->assertSame('2026-07-02', $exception->date->format('Y-m-d'));
    }

    public function test_target_can_decline_with_reason(): void
    {
        $ctx = $this->makeSetup();

        $req = ShiftTakeoverRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'status' => ShiftTakeoverRequestStatus::Pending,
        ]);

        $response = $this->actingAs($ctx['targetUser'])->post(
            route('shift-takeover-requests.decline', $req),
            ['decline_reason' => 'Niet beschikbaar die dag']
        );

        $response->assertRedirect();
        $req->refresh();
        $this->assertSame(ShiftTakeoverRequestStatus::Declined, $req->status);
        $this->assertSame('Niet beschikbaar die dag', $req->decline_reason);
        $this->assertNotNull($req->responded_at);
    }

    public function test_non_target_cannot_respond(): void
    {
        $ctx = $this->makeSetup();
        $strangerUser = User::factory()->create(['role' => UserRole::Caregiver]);

        $req = ShiftTakeoverRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'status' => ShiftTakeoverRequestStatus::Pending,
        ]);

        $response = $this->actingAs($strangerUser)->post(
            route('shift-takeover-requests.accept', $req)
        );

        $response->assertForbidden();
    }

    public function test_requester_can_cancel_pending_request(): void
    {
        $ctx = $this->makeSetup();

        $req = ShiftTakeoverRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'status' => ShiftTakeoverRequestStatus::Pending,
        ]);

        $response = $this->actingAs($ctx['requesterUser'])->delete(
            route('shift-takeover-requests.destroy', $req)
        );

        $response->assertRedirect();
        $req->refresh();
        $this->assertSame(ShiftTakeoverRequestStatus::Cancelled, $req->status);
    }

    public function test_cannot_create_request_for_own_shift(): void
    {
        $ctx = $this->makeSetup();

        $ownSchedule = Schedule::factory()->create([
            'client_id' => $ctx['client']->id,
            'caregiver_id' => $ctx['requester']->id,
        ]);

        $response = $this->actingAs($ctx['requesterUser'])->postJson(
            route('shift-takeover-requests.store'),
            [
                'target_caregiver_id' => $ctx['requester']->id,
                'target_schedule_id' => $ownSchedule->id,
                'target_date' => '2026-07-02',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_cannot_create_request_for_shift_of_different_client(): void
    {
        $ctx = $this->makeSetup();

        // Different client/schedule that does NOT belong to target's client
        $otherClient = Client::factory()->create();
        $otherCaregiver = Caregiver::factory()->create(['client_id' => $otherClient->id]);
        $otherSchedule = Schedule::factory()->create([
            'client_id' => $otherClient->id,
            'caregiver_id' => $otherCaregiver->id,
        ]);

        $response = $this->actingAs($ctx['requesterUser'])->postJson(
            route('shift-takeover-requests.store'),
            [
                'target_caregiver_id' => $ctx['target']->id,
                'target_schedule_id' => $otherSchedule->id,
                'target_date' => '2026-07-02',
            ]
        );

        $response->assertStatus(422);
    }
}
