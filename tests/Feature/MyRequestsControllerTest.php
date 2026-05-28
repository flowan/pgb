<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ShiftTakeoverOffer;
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
}
