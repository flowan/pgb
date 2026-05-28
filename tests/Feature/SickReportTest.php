<?php

namespace Tests\Feature;

use App\Enums\ScheduleExceptionType;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftTakeoverOffer;
use App\Models\User;
use App\Notifications\CaregiverReportedSick;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SickReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{User, User, Client, Caregiver, Schedule}
     */
    private function makeWorld(string $startTime = '09:00', string $endTime = '12:00'): array
    {
        $budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create(['budget_holder_id' => $budgetHolder->id]);
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);

        // Use Monday so day_of_week = 0 (our 0=Mon convention)
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'day_of_week' => 0,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        return [$budgetHolder, $caregiverUser, $client, $caregiver, $schedule];
    }

    /** Find the next Monday on/after $from. */
    private function nextMonday(CarbonImmutable $from): CarbonImmutable
    {
        $cursor = $from;
        while ($cursor->dayOfWeekIso !== 1) {
            $cursor = $cursor->addDay();
        }
        return $cursor;
    }

    public function test_caregiver_can_sick_report_single_shift(): void
    {
        Notification::fake();
        [, $caregiverUser, , $caregiver, $schedule] = $this->makeWorld();
        $date = $this->nextMonday(CarbonImmutable::tomorrow())->format('Y-m-d');

        $response = $this->actingAs($caregiverUser)->post(route('sick-reports.store'), [
            'scope' => 'single',
            'kind' => 'schedule',
            'id' => $schedule->id,
            'date' => $date,
        ]);

        $response->assertRedirect();

        $exception = ScheduleException::first();
        $this->assertNotNull($exception);
        $this->assertSame(ScheduleExceptionType::Cancelled, $exception->type);
        $this->assertTrue((bool) $exception->due_to_sickness);
        $this->assertSame($schedule->id, $exception->schedule_id);
        $this->assertSame($caregiver->id, $exception->caregiver_id);
        $this->assertSame($date, $exception->date->format('Y-m-d'));

        $offer = ShiftTakeoverOffer::first();
        $this->assertNotNull($offer);
        $this->assertSame($schedule->id, $offer->schedule_id);
        $this->assertSame(ShiftTakeoverOfferStatus::Open, $offer->status);
        $this->assertSame($caregiver->id, $offer->offered_by_caregiver_id);
        $this->assertSame($date, $offer->date->format('Y-m-d'));
    }

    public function test_caregiver_can_sick_report_date_range(): void
    {
        Notification::fake();
        [, $caregiverUser, , $caregiver, $schedule] = $this->makeWorld();
        $firstMonday = $this->nextMonday(CarbonImmutable::tomorrow());
        $secondMonday = $firstMonday->addDays(7);

        $response = $this->actingAs($caregiverUser)->post(route('sick-reports.store'), [
            'scope' => 'range',
            'start_date' => $firstMonday->format('Y-m-d'),
            'end_date' => $secondMonday->addDay()->format('Y-m-d'),
        ]);

        $response->assertRedirect();

        $exceptions = ScheduleException::where('schedule_id', $schedule->id)
            ->where('due_to_sickness', true)
            ->get();
        $this->assertCount(2, $exceptions);

        $offers = ShiftTakeoverOffer::where('schedule_id', $schedule->id)
            ->where('offered_by_caregiver_id', $caregiver->id)
            ->get();
        $this->assertCount(2, $offers);
        $this->assertTrue($offers->every(fn ($o) => $o->status === ShiftTakeoverOfferStatus::Open));
    }

    public function test_range_skips_already_cancelled_dates(): void
    {
        Notification::fake();
        [, $caregiverUser, $client, $caregiver, $schedule] = $this->makeWorld();
        $firstMonday = $this->nextMonday(CarbonImmutable::tomorrow());
        $secondMonday = $firstMonday->addDays(7);

        // Pre-create a cancellation for the first Monday
        ScheduleException::create([
            'schedule_id' => $schedule->id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'date' => $firstMonday->format('Y-m-d'),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'type' => ScheduleExceptionType::Cancelled,
        ]);

        $this->actingAs($caregiverUser)->post(route('sick-reports.store'), [
            'scope' => 'range',
            'start_date' => $firstMonday->format('Y-m-d'),
            'end_date' => $secondMonday->addDay()->format('Y-m-d'),
        ])->assertRedirect();

        // Sickness cancellations: only the second monday should have been created
        $sickExceptions = ScheduleException::where('schedule_id', $schedule->id)
            ->where('due_to_sickness', true)
            ->get();
        $this->assertCount(1, $sickExceptions);
        $this->assertSame($secondMonday->format('Y-m-d'), $sickExceptions->first()->date->format('Y-m-d'));

        // Only one new offer (for the second monday)
        $offers = ShiftTakeoverOffer::where('schedule_id', $schedule->id)->get();
        $this->assertCount(1, $offers);
        $this->assertSame($secondMonday->format('Y-m-d'), $offers->first()->date->format('Y-m-d'));
    }

    public function test_notification_sent_to_budget_holder(): void
    {
        Notification::fake();
        [$budgetHolder, $caregiverUser, , , $schedule] = $this->makeWorld();
        $date = $this->nextMonday(CarbonImmutable::tomorrow())->format('Y-m-d');

        $this->actingAs($caregiverUser)->post(route('sick-reports.store'), [
            'scope' => 'single',
            'kind' => 'schedule',
            'id' => $schedule->id,
            'date' => $date,
        ])->assertRedirect();

        Notification::assertSentTo($budgetHolder, CaregiverReportedSick::class);
    }

    public function test_claiming_takeover_removes_sickness_cancellation(): void
    {
        Notification::fake();
        [, $caregiverUser, $client, , $schedule] = $this->makeWorld();
        $date = $this->nextMonday(CarbonImmutable::tomorrow())->format('Y-m-d');

        // Sick report
        $this->actingAs($caregiverUser)->post(route('sick-reports.store'), [
            'scope' => 'single',
            'kind' => 'schedule',
            'id' => $schedule->id,
            'date' => $date,
        ])->assertRedirect();

        $offer = ShiftTakeoverOffer::first();
        $this->assertNotNull($offer);

        // Now a colleague claims it
        $colleagueUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $colleague = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $colleagueUser->id,
        ]);

        $this->actingAs($colleagueUser)->post(route('shift-takeover-offers.claim', $offer))
            ->assertRedirect();

        // The sickness cancellation should be removed
        $remainingSick = ScheduleException::where('schedule_id', $schedule->id)
            ->where('due_to_sickness', true)
            ->where('type', ScheduleExceptionType::Cancelled)
            ->count();
        $this->assertSame(0, $remainingSick);

        // The offer should be claimed and a modified exception created for the colleague
        $offer->refresh();
        $this->assertSame(ShiftTakeoverOfferStatus::Claimed, $offer->status);
        $this->assertSame($colleague->id, $offer->claimed_by_caregiver_id);
    }

    public function test_only_caregiver_role_can_report_sick(): void
    {
        Notification::fake();
        [$budgetHolder, , , , $schedule] = $this->makeWorld();
        $date = $this->nextMonday(CarbonImmutable::tomorrow())->format('Y-m-d');

        $response = $this->actingAs($budgetHolder)->post(route('sick-reports.store'), [
            'scope' => 'single',
            'kind' => 'schedule',
            'id' => $schedule->id,
            'date' => $date,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, ScheduleException::count());
    }

    public function test_caregiver_cannot_sick_report_another_caregivers_shift(): void
    {
        Notification::fake();
        [, , $client, , $schedule] = $this->makeWorld();

        // A second caregiver/user — they should not be able to sick-report the first's shift
        $otherUser = User::factory()->create(['role' => UserRole::Caregiver]);
        Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $otherUser->id,
        ]);

        $date = $this->nextMonday(CarbonImmutable::tomorrow())->format('Y-m-d');

        $response = $this->actingAs($otherUser)->post(route('sick-reports.store'), [
            'scope' => 'single',
            'kind' => 'schedule',
            'id' => $schedule->id,
            'date' => $date,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, ScheduleException::count());
    }
}
