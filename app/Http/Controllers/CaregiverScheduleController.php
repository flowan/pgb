<?php

namespace App\Http\Controllers;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
use App\Models\ShiftTakeoverOffer;
use Inertia\Inertia;
use Inertia\Response;

class CaregiverScheduleController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();
        $caregiverRecords = Caregiver::where('user_id', $user->id)->with('client')->get();
        $myCaregiverIds = $caregiverRecords->pluck('id')->toArray();
        $clientIds = $caregiverRecords->pluck('client_id')->unique()->toArray();

        // Open takeover offers across all my clients (mine + colleagues')
        $takeoverOffers = ShiftTakeoverOffer::where('status', ShiftTakeoverOfferStatus::Open)
            ->where(function ($q) use ($clientIds) {
                $q->whereHas('schedule', fn ($s) => $s->whereIn('client_id', $clientIds))
                  ->orWhereHas('scheduleException', fn ($e) => $e->whereIn('client_id', $clientIds));
            })
            ->with(['offeredBy', 'schedule', 'scheduleException'])
            ->get();

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
            'takeoverOffers' => $takeoverOffers,
        ]);
    }
}
