<?php

namespace Database\Seeders;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\CaregiverType;
use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // === Budgethouder ===
        $holder = User::factory()->create([
            'name' => 'Linda de Groot',
            'email' => 'linda@pgb.test',
            'password' => bcrypt('password'),
            'role' => UserRole::BudgetHolder,
        ]);

        // === Zorgverlener accounts ===
        $janUser = User::factory()->create([
            'name' => 'Jan Bakker',
            'email' => 'jan@pgb.test',
            'password' => bcrypt('password'),
            'role' => UserRole::Caregiver,
        ]);

        $mariaUser = User::factory()->create([
            'name' => 'Maria Jansen',
            'email' => 'maria@pgb.test',
            'password' => bcrypt('password'),
            'role' => UserRole::Caregiver,
        ]);

        // === Cliënt: Sophie ===
        $sophie = Client::create([
            'budget_holder_id' => $holder->id,
            'name' => 'Sophie de Vries',
            'date_of_birth' => '2018-03-15',
            'notes' => 'PGB voor begeleiding en persoonlijke verzorging',
        ]);

        // === Cliënt: Tim ===
        $tim = Client::create([
            'budget_holder_id' => $holder->id,
            'name' => 'Tim de Groot',
            'date_of_birth' => '2015-11-02',
            'notes' => 'Dagbesteding en begeleiding',
        ]);

        // === Zorgverleners Sophie ===
        $janCaregiver = Caregiver::create([
            'client_id' => $sophie->id,
            'user_id' => $janUser->id,
            'name' => 'Jan Bakker',
            'type' => CaregiverType::CareWorker,
            'hourly_rate' => 35.00,
        ]);

        $mariaCaregiver = Caregiver::create([
            'client_id' => $sophie->id,
            'user_id' => $mariaUser->id,
            'name' => 'Maria Jansen',
            'type' => CaregiverType::Parent,
        ]);

        $zorgBv = Caregiver::create([
            'client_id' => $sophie->id,
            'name' => 'Zorg BV',
            'type' => CaregiverType::Zzp,
            'hourly_rate' => 45.00,
        ]);

        // === Zorgverleners Tim ===
        $janCaregiverTim = Caregiver::create([
            'client_id' => $tim->id,
            'user_id' => $janUser->id,
            'name' => 'Jan Bakker',
            'type' => CaregiverType::CareWorker,
            'hourly_rate' => 35.00,
        ]);

        $dagbesteding = Caregiver::create([
            'client_id' => $tim->id,
            'name' => 'Stichting Daglicht',
            'type' => CaregiverType::DayCare,
            'hourly_rate' => 28.00,
        ]);

        // === Budget Sophie ===
        $pvSophie = BudgetCategory::create([
            'client_id' => $sophie->id,
            'name' => 'Persoonlijke verzorging',
            'allocated_amount' => 8000,
        ]);
        BudgetExpense::create([
            'budget_category_id' => $pvSophie->id,
            'caregiver_id' => $janCaregiver->id,
            'description' => 'Januari',
            'amount' => 1400,
            'date' => '2026-01-31',
        ]);
        BudgetExpense::create([
            'budget_category_id' => $pvSophie->id,
            'caregiver_id' => $janCaregiver->id,
            'description' => 'Februari',
            'amount' => 1400,
            'date' => '2026-02-28',
        ]);
        BudgetExpense::create([
            'budget_category_id' => $pvSophie->id,
            'caregiver_id' => $janCaregiver->id,
            'description' => 'Maart',
            'amount' => 1400,
            'date' => '2026-03-31',
        ]);
        BudgetExpense::create([
            'budget_category_id' => $pvSophie->id,
            'caregiver_id' => $janCaregiver->id,
            'description' => 'April',
            'amount' => 1400,
            'date' => '2026-04-30',
        ]);
        $pvSophie->updateSpentAmount();

        $begSophie = BudgetCategory::create([
            'client_id' => $sophie->id,
            'name' => 'Begeleiding',
            'allocated_amount' => 12000,
        ]);
        BudgetExpense::create([
            'budget_category_id' => $begSophie->id,
            'caregiver_id' => $zorgBv->id,
            'description' => 'Q1 2026',
            'amount' => 4500,
            'date' => '2026-03-31',
        ]);
        $begSophie->updateSpentAmount();

        $dagSophie = BudgetCategory::create([
            'client_id' => $sophie->id,
            'name' => 'Dagbesteding',
            'allocated_amount' => 5000,
        ]);
        BudgetExpense::create([
            'budget_category_id' => $dagSophie->id,
            'caregiver_id' => $zorgBv->id,
            'description' => 'Jan–Mrt 2026',
            'amount' => 2000,
            'date' => '2026-03-31',
        ]);
        $dagSophie->updateSpentAmount();

        // === Budget Tim ===
        $begTim = BudgetCategory::create([
            'client_id' => $tim->id,
            'name' => 'Begeleiding',
            'allocated_amount' => 10000,
        ]);
        BudgetExpense::create([
            'budget_category_id' => $begTim->id,
            'caregiver_id' => $janCaregiverTim->id,
            'description' => 'Q1 2026',
            'amount' => 3150,
            'date' => '2026-03-31',
        ]);
        $begTim->updateSpentAmount();

        $dagTim = BudgetCategory::create([
            'client_id' => $tim->id,
            'name' => 'Dagbesteding',
            'allocated_amount' => 15000,
        ]);
        BudgetExpense::create([
            'budget_category_id' => $dagTim->id,
            'caregiver_id' => $dagbesteding->id,
            'description' => 'Jan–Apr 2026',
            'amount' => 4480,
            'date' => '2026-04-30',
        ]);
        $dagTim->updateSpentAmount();

        // === Planning Sophie ===
        Schedule::create([
            'client_id' => $sophie->id,
            'caregiver_id' => $janCaregiver->id,
            'day_of_week' => 0, // maandag
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        Schedule::create([
            'client_id' => $sophie->id,
            'caregiver_id' => $janCaregiver->id,
            'day_of_week' => 2, // woensdag
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        Schedule::create([
            'client_id' => $sophie->id,
            'caregiver_id' => $zorgBv->id,
            'day_of_week' => 1, // dinsdag
            'start_time' => '13:00',
            'end_time' => '17:00',
        ]);
        Schedule::create([
            'client_id' => $sophie->id,
            'caregiver_id' => $mariaCaregiver->id,
            'day_of_week' => 4, // vrijdag
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        // Afwijking: woensdag deze week extra fysiotherapie
        ScheduleException::create([
            'client_id' => $sophie->id,
            'caregiver_id' => $janCaregiver->id,
            'schedule_id' => null,
            'date' => now()->startOfWeek()->addDays(2)->format('Y-m-d'),
            'start_time' => '13:00',
            'end_time' => '14:30',
            'type' => ScheduleExceptionType::Added,
            'notes' => 'Fysiotherapie',
        ]);

        // === Planning Tim ===
        Schedule::create([
            'client_id' => $tim->id,
            'caregiver_id' => $janCaregiverTim->id,
            'day_of_week' => 1, // dinsdag
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        Schedule::create([
            'client_id' => $tim->id,
            'caregiver_id' => $janCaregiverTim->id,
            'day_of_week' => 3, // donderdag
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        Schedule::create([
            'client_id' => $tim->id,
            'caregiver_id' => $dagbesteding->id,
            'day_of_week' => 0, // maandag
            'start_time' => '09:00',
            'end_time' => '15:00',
        ]);
        Schedule::create([
            'client_id' => $tim->id,
            'caregiver_id' => $dagbesteding->id,
            'day_of_week' => 2, // woensdag
            'start_time' => '09:00',
            'end_time' => '15:00',
        ]);
        Schedule::create([
            'client_id' => $tim->id,
            'caregiver_id' => $dagbesteding->id,
            'day_of_week' => 4, // vrijdag
            'start_time' => '09:00',
            'end_time' => '15:00',
        ]);

        // === Beschikbare slots Sophie ===
        AvailabilitySlot::create([
            'client_id' => $sophie->id,
            'day_of_week' => 3, // donderdag (terugkerend)
            'start_time' => '09:00',
            'end_time' => '12:00',
            'status' => AvailabilitySlotStatus::Open,
            'notes' => 'Ochtend begeleiding',
        ]);
        AvailabilitySlot::create([
            'client_id' => $sophie->id,
            'date' => now()->startOfWeek()->addDays(4)->format('Y-m-d'), // vrijdag deze week (eenmalig)
            'start_time' => '13:00',
            'end_time' => '16:00',
            'status' => AvailabilitySlotStatus::Open,
            'notes' => 'Extra middagbegeleiding',
        ]);

        // === Beschikbaar slot Tim ===
        AvailabilitySlot::create([
            'client_id' => $tim->id,
            'day_of_week' => 4, // vrijdag (terugkerend)
            'start_time' => '15:00',
            'end_time' => '17:00',
            'status' => AvailabilitySlotStatus::Open,
        ]);
    }
}
