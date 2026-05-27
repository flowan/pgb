<?php

use App\Http\Controllers\BudgetCategoryController;
use App\Http\Controllers\BudgetExpenseController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleExceptionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
});

require __DIR__.'/settings.php';
