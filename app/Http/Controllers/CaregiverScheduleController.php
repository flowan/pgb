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
        $caregiverRecords = Caregiver::where('user_id', $user->id)->with('client')->get();
        $myCaregiverIds = $caregiverRecords->pluck('id')->toArray();

        $clients = $caregiverRecords->map(function (Caregiver $caregiver) {
            $client = $caregiver->client;
            $client->load([
                'schedules.caregiver',
                'scheduleExceptions.caregiver',
                'caregivers',
            ]);
            $client->setRelation(
                'availabilitySlots',
                $client->availabilitySlots()->where('status', 'open')->get()
            );

            return $client;
        })->unique('id')->values();

        return Inertia::render('caregiver-schedule/index', [
            'clients' => $clients,
            'myCaregiverIds' => $myCaregiverIds,
        ]);
    }
}
