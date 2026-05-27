<?php

use App\Http\Controllers\BudgetCategoryController;
use App\Http\Controllers\BudgetExpenseController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
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
});

require __DIR__.'/settings.php';
