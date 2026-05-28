<?php

namespace Tests\Feature;

use App\Enums\ScheduleExceptionType;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftSwapRequestTest extends TestCase
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
        $requesterSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $requester->id,
            'start_time' => '09:00',
            'end_time' => '12:00',
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
            'requesterSchedule',
            'targetSchedule',
        );
    }

    public function test_caregiver_creates_direct_swap_request(): void
    {
        $ctx = $this->makeSetup();

        $response = $this->actingAs($ctx['requesterUser'])->post(
            route('shift-swap-requests.store'),
            [
                'requester_schedule_id' => $ctx['requesterSchedule']->id,
                'requester_date' => '2026-07-01',
                'target_caregiver_id' => $ctx['target']->id,
                'target_schedule_id' => $ctx['targetSchedule']->id,
                'target_date' => '2026-07-02',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_swap_requests', [
            'requester_caregiver_id' => $ctx['requester']->id,
            'requester_schedule_id' => $ctx['requesterSchedule']->id,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'status' => ShiftSwapRequestStatus::Pending->value,
        ]);
    }

    public function test_target_can_accept_and_creates_two_exceptions(): void
    {
        $ctx = $this->makeSetup();

        $swap = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'requester_schedule_id' => $ctx['requesterSchedule']->id,
            'requester_schedule_exception_id' => null,
            'requester_date' => '2026-07-01',
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'target_date' => '2026-07-02',
            'status' => ShiftSwapRequestStatus::Pending,
        ]);

        $response = $this->actingAs($ctx['targetUser'])->post(
            route('shift-swap-requests.accept', $swap)
        );

        $response->assertRedirect();
        $swap->refresh();
        $this->assertSame(ShiftSwapRequestStatus::Accepted, $swap->status);
        $this->assertNotNull($swap->responded_at);
        $this->assertCount(2, $swap->resulting_exception_ids);

        // First exception: requester's shift goes to target caregiver
        $firstId = $swap->resulting_exception_ids[0];
        $first = ScheduleException::findOrFail($firstId);
        $this->assertEquals($ctx['target']->id, $first->caregiver_id);
        $this->assertEquals($ctx['requesterSchedule']->id, $first->schedule_id);
        $this->assertSame(ScheduleExceptionType::Modified, $first->type);
        $this->assertSame('2026-07-01', $first->date->toDateString());

        // Second exception: target's shift goes to requester caregiver
        $secondId = $swap->resulting_exception_ids[1];
        $second = ScheduleException::findOrFail($secondId);
        $this->assertEquals($ctx['requester']->id, $second->caregiver_id);
        $this->assertEquals($ctx['targetSchedule']->id, $second->schedule_id);
        $this->assertSame(ScheduleExceptionType::Modified, $second->type);
        $this->assertSame('2026-07-02', $second->date->toDateString());
    }

    public function test_target_can_decline_with_reason(): void
    {
        $ctx = $this->makeSetup();

        $swap = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'requester_schedule_id' => $ctx['requesterSchedule']->id,
            'requester_schedule_exception_id' => null,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'status' => ShiftSwapRequestStatus::Pending,
        ]);

        $response = $this->actingAs($ctx['targetUser'])->post(
            route('shift-swap-requests.decline', $swap),
            ['decline_reason' => 'Already busy that day']
        );

        $response->assertRedirect();
        $swap->refresh();
        $this->assertSame(ShiftSwapRequestStatus::Declined, $swap->status);
        $this->assertSame('Already busy that day', $swap->decline_reason);
        $this->assertNotNull($swap->responded_at);
    }

    public function test_non_target_cannot_respond(): void
    {
        $ctx = $this->makeSetup();
        $strangerUser = User::factory()->create(['role' => UserRole::Caregiver]);

        $swap = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'requester_schedule_id' => $ctx['requesterSchedule']->id,
            'requester_schedule_exception_id' => null,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'status' => ShiftSwapRequestStatus::Pending,
        ]);

        $response = $this->actingAs($strangerUser)->post(
            route('shift-swap-requests.accept', $swap)
        );

        $response->assertForbidden();
    }

    public function test_requester_can_cancel(): void
    {
        $ctx = $this->makeSetup();

        $swap = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $ctx['requester']->id,
            'requester_schedule_id' => $ctx['requesterSchedule']->id,
            'requester_schedule_exception_id' => null,
            'target_caregiver_id' => $ctx['target']->id,
            'target_schedule_id' => $ctx['targetSchedule']->id,
            'target_schedule_exception_id' => null,
            'status' => ShiftSwapRequestStatus::Pending,
        ]);

        $response = $this->actingAs($ctx['requesterUser'])->delete(
            route('shift-swap-requests.destroy', $swap)
        );

        $response->assertRedirect();
        $swap->refresh();
        $this->assertSame(ShiftSwapRequestStatus::Cancelled, $swap->status);
    }
}
