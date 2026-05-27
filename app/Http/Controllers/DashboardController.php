<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user->role === UserRole::Caregiver) {
            return redirect('/my-schedule');
        }

        $clients = $user->clients()
            ->with(['budgetCategories', 'caregivers', 'schedules.caregiver'])
            ->get();

        return Inertia::render('dashboard', [
            'clients' => $clients,
        ]);
    }
}
