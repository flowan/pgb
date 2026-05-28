<?php

namespace Tests\Feature;

use App\Enums\ShiftTakeoverRequestStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ShiftTakeoverOffer;
use App\Models\ShiftTakeoverRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyRequestsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_caregiver_sees_own_offers(): void
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();
        $cg = Caregiver::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);
        ShiftTakeoverOffer::factory()->create(['offered_by_caregiver_id' => $cg->id]);

        $response = $this->actingAs($user)->get('/my-requests');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('my-requests/index')
            ->has('myTakeoverOffers', 1)
        );
    }

    public function test_caregiver_sees_takeover_requests(): void
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();
        $requester = Caregiver::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);
        $target = Caregiver::factory()->create(['client_id' => $client->id]);
        $targetSchedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $target->id,
        ]);

        ShiftTakeoverRequest::factory()->create([
            'requester_caregiver_id' => $requester->id,
            'target_caregiver_id' => $target->id,
            'target_schedule_id' => $targetSchedule->id,
            'status' => ShiftTakeoverRequestStatus::Pending,
        ]);

        $response = $this->actingAs($user)->get('/my-requests');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('my-requests/index')
            ->has('myTakeoverRequestsOut', 1)
        );
    }
}
