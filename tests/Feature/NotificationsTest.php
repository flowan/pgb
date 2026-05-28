<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ShiftTakeoverOffered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_takeover_offer_notifies_colleagues(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $userA = User::factory()->create(['role' => UserRole::Caregiver]);
        $userB = User::factory()->create(['role' => UserRole::Caregiver]);
        $cgA = Caregiver::factory()->create(['client_id' => $client->id, 'user_id' => $userA->id]);
        Caregiver::factory()->create(['client_id' => $client->id, 'user_id' => $userB->id]);
        $schedule = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgA->id]);

        $this->actingAs($userA)->post('/shift-takeover-offers', [
            'schedule_id' => $schedule->id,
            'date' => '2026-06-15',
        ]);

        Notification::assertSentTo($userB, ShiftTakeoverOffered::class);
        Notification::assertNotSentTo($userA, ShiftTakeoverOffered::class);
    }

    public function test_notification_bell_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/notifications');

        $response->assertOk()->assertJsonStructure(['unread_count', 'notifications']);
    }
}
