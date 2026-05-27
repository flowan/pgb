<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Models\Client;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load(['schedules.caregiver', 'scheduleExceptions.caregiver', 'caregivers']);

        return Inertia::render('schedule/index', [
            'schedules' => $client->schedules,
            'exceptions' => $client->scheduleExceptions,
            'caregivers' => $client->caregivers,
            'client' => $client,
            'availabilitySlots' => $client->availabilitySlots()->where('status', 'open')->get(),
        ]);
    }

    public function store(ScheduleRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->schedules()->create($request->validated());

        return redirect()->route('clients.schedule.index', $client);
    }

    public function update(ScheduleRequest $request, Client $client, Schedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $schedule->update($request->validated());

        return redirect()->route('clients.schedule.index', $client);
    }

    public function destroy(Client $client, Schedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return redirect()->route('clients.schedule.index', $client);
    }
}
