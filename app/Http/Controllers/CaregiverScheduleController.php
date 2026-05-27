<?php

namespace App\Http\Controllers;

use App\Models\Caregiver;
use Inertia\Inertia;
use Inertia\Response;

class CaregiverScheduleController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $caregiverRecords = Caregiver::where('user_id', $user->id)->get();

        $clients = $caregiverRecords->map(function (Caregiver $caregiver) {
            $client = $caregiver->client;
            $client->load([
                'schedules' => fn ($q) => $q->where('caregiver_id', $caregiver->id),
                'schedules.caregiver',
                'scheduleExceptions' => fn ($q) => $q->where('caregiver_id', $caregiver->id),
                'scheduleExceptions.caregiver',
            ]);

            return $client;
        })->unique('id')->values();

        return Inertia::render('caregiver-schedule/index', [
            'clients' => $clients,
        ]);
    }
}
