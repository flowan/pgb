<?php

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
});

require __DIR__.'/settings.php';
