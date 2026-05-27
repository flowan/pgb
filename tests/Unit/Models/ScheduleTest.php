<?php

namespace Tests\Unit\Models;

use App\Enums\ScheduleExceptionType;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_belongs_to_client_and_caregiver(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $this->assertInstanceOf(Client::class, $schedule->client);
        $this->assertTrue($schedule->client->is($client));
        $this->assertInstanceOf(Caregiver::class, $schedule->caregiver);
        $this->assertTrue($schedule->caregiver->is($caregiver));
    }

    public function test_schedule_has_exceptions(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        ScheduleException::factory()->count(2)->create([
            'schedule_id' => $schedule->id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $this->assertCount(2, $schedule->exceptions);
        $this->assertInstanceOf(ScheduleException::class, $schedule->exceptions->first());
    }

    public function test_schedule_exception_can_be_standalone_appointment(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        $exception = ScheduleException::factory()->create([
            'schedule_id' => null,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'type' => ScheduleExceptionType::Added,
        ]);

        $this->assertNull($exception->schedule);
        $this->assertSame(ScheduleExceptionType::Added, $exception->type);
        $this->assertInstanceOf(Client::class, $exception->client);
        $this->assertInstanceOf(Caregiver::class, $exception->caregiver);
    }

    public function test_client_has_schedules(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);

        Schedule::factory()->count(3)->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $this->assertCount(3, $client->schedules);
        $this->assertInstanceOf(Schedule::class, $client->schedules->first());
    }
}
