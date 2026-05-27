<?php

namespace Tests\Unit\Models;

use App\Enums\CaregiverType;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_belongs_to_client(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(Client::class, $caregiver->client);
        $this->assertTrue($caregiver->client->is($client));
    }

    public function test_caregiver_optionally_belongs_to_user(): void
    {
        $caregiver = Caregiver::factory()->create(['user_id' => null]);
        $this->assertNull($caregiver->user);

        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $caregiver->update(['user_id' => $user->id]);
        $caregiver->refresh();

        $this->assertInstanceOf(User::class, $caregiver->user);
        $this->assertTrue($caregiver->user->is($user));
    }

    public function test_caregiver_has_type_enum(): void
    {
        $caregiver = Caregiver::factory()->create(['type' => CaregiverType::CareWorker]);

        $this->assertInstanceOf(CaregiverType::class, $caregiver->type);
        $this->assertSame(CaregiverType::CareWorker, $caregiver->type);
    }

    public function test_client_has_caregivers(): void
    {
        $client = Client::factory()->create();
        Caregiver::factory()->count(2)->create(['client_id' => $client->id]);

        $this->assertCount(2, $client->caregivers);
        $this->assertInstanceOf(Caregiver::class, $client->caregivers->first());
    }
}
