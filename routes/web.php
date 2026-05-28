<?php

use App\Http\Controllers\BudgetCategoryController;
use App\Http\Controllers\BudgetExpenseController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\CaregiverScheduleController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyRequestsController;
use App\Http\Controllers\AvailabilityClaimController;
use App\Http\Controllers\AvailabilitySlotController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpenSwapOfferController;
use App\Http\Controllers\OpenSwapRequestController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleExceptionController;
use App\Http\Controllers\ShiftSwapRequestController;
use App\Http\Controllers\ShiftSwapResponseController;
use App\Http\Controllers\ShiftTakeoverClaimController;
use App\Http\Controllers\ShiftTakeoverRequestController;
use App\Http\Controllers\ShiftTakeoverRequestResponseController;
use App\Http\Controllers\ShiftTakeoverOfferController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/my-schedule', CaregiverScheduleController::class)
    ->middleware(['auth', 'verified', 'role:caregiver'])
    ->name('my-schedule');

Route::post('/availability-slots/{availabilitySlot}/claim', [AvailabilityClaimController::class, 'store'])
    ->middleware(['auth', 'verified', 'role:caregiver'])
    ->name('availability-slots.claim');

Route::post('/availability-slots/{availabilitySlot}/release', [\App\Http\Controllers\AvailabilityReleaseController::class, 'store'])
    ->middleware(['auth', 'verified', 'role:caregiver'])
    ->name('availability-slots.release');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

Route::middleware(['auth', 'verified', 'role:caregiver'])->group(function () {
    Route::get('/my-requests', [MyRequestsController::class, 'index'])->name('my-requests.index');

    Route::post('/shift-takeover-offers', [ShiftTakeoverOfferController::class, 'store'])->name('shift-takeover-offers.store');
    Route::delete('/shift-takeover-offers/{shiftTakeoverOffer}', [ShiftTakeoverOfferController::class, 'destroy'])->name('shift-takeover-offers.destroy');
    Route::post('/shift-takeover-offers/{shiftTakeoverOffer}/claim', [ShiftTakeoverClaimController::class, 'store'])->name('shift-takeover-offers.claim');

    Route::post('/shift-swap-requests', [ShiftSwapRequestController::class, 'store'])->name('shift-swap-requests.store');
    Route::delete('/shift-swap-requests/{shiftSwapRequest}', [ShiftSwapRequestController::class, 'destroy'])->name('shift-swap-requests.destroy');
    Route::post('/shift-swap-requests/{shiftSwapRequest}/accept', [ShiftSwapResponseController::class, 'accept'])->name('shift-swap-requests.accept');
    Route::post('/shift-swap-requests/{shiftSwapRequest}/decline', [ShiftSwapResponseController::class, 'decline'])->name('shift-swap-requests.decline');

    Route::post('/shift-takeover-requests', [ShiftTakeoverRequestController::class, 'store'])->name('shift-takeover-requests.store');
    Route::delete('/shift-takeover-requests/{shiftTakeoverRequest}', [ShiftTakeoverRequestController::class, 'destroy'])->name('shift-takeover-requests.destroy');
    Route::post('/shift-takeover-requests/{shiftTakeoverRequest}/accept', [ShiftTakeoverRequestResponseController::class, 'accept'])->name('shift-takeover-requests.accept');
    Route::post('/shift-takeover-requests/{shiftTakeoverRequest}/decline', [ShiftTakeoverRequestResponseController::class, 'decline'])->name('shift-takeover-requests.decline');

    Route::post('/sick-reports', [\App\Http\Controllers\SickReportController::class, 'store'])->name('sick-reports.store');

    Route::post('/open-swap-requests', [OpenSwapRequestController::class, 'store'])->name('open-swap-requests.store');
    Route::delete('/open-swap-requests/{openSwapRequest}', [OpenSwapRequestController::class, 'destroy'])->name('open-swap-requests.destroy');
    Route::post('/open-swap-requests/{openSwapRequest}/offers', [OpenSwapOfferController::class, 'store'])->name('open-swap-requests.offers.store');
    Route::post('/open-swap-requests/{openSwapRequest}/offers/{offer}/accept', [OpenSwapRequestController::class, 'acceptOffer'])->name('open-swap-requests.offers.accept');
    Route::delete('/open-swap-offers/{offer}', [OpenSwapOfferController::class, 'destroy'])->name('open-swap-offers.destroy');
});

Route::middleware(['auth', 'verified', 'role:budget_holder'])->group(function () {
    Route::resource('clients', ClientController::class);
    Route::resource('clients.caregivers', CaregiverController::class)->except(['show']);

    Route::get('clients/{client}/budget', [BudgetCategoryController::class, 'index'])->name('clients.budget.index');
    Route::post('clients/{client}/budget-categories', [BudgetCategoryController::class, 'store'])->name('clients.budget-categories.store');
    Route::put('clients/{client}/budget-categories/{budgetCategory}', [BudgetCategoryController::class, 'update'])->name('clients.budget-categories.update');
    Route::delete('clients/{client}/budget-categories/{budgetCategory}', [BudgetCategoryController::class, 'destroy'])->name('clients.budget-categories.destroy');

    Route::post('clients/{client}/budget-expenses', [BudgetExpenseController::class, 'store'])->name('clients.budget-expenses.store');
    Route::put('clients/{client}/budget-expenses/{budgetExpense}', [BudgetExpenseController::class, 'update'])->name('clients.budget-expenses.update');
    Route::delete('clients/{client}/budget-expenses/{budgetExpense}', [BudgetExpenseController::class, 'destroy'])->name('clients.budget-expenses.destroy');

    Route::get('clients/{client}/schedule', [ScheduleController::class, 'index'])->name('clients.schedule.index');
    Route::post('clients/{client}/schedules', [ScheduleController::class, 'store'])->name('clients.schedules.store');
    Route::put('clients/{client}/schedules/{schedule}', [ScheduleController::class, 'update'])->name('clients.schedules.update');
    Route::delete('clients/{client}/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('clients.schedules.destroy');

    Route::post('clients/{client}/schedule-exceptions', [ScheduleExceptionController::class, 'store'])->name('clients.schedule-exceptions.store');
    Route::delete('clients/{client}/schedule-exceptions/{scheduleException}', [ScheduleExceptionController::class, 'destroy'])->name('clients.schedule-exceptions.destroy');

    Route::post('clients/{client}/availability-slots', [AvailabilitySlotController::class, 'store'])->name('clients.availability-slots.store');
    Route::delete('clients/{client}/availability-slots/{availabilitySlot}', [AvailabilitySlotController::class, 'destroy'])->name('clients.availability-slots.destroy');
});

require __DIR__.'/settings.php';
