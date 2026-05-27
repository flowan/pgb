<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleExceptionRequest;
use App\Models\Client;
use App\Models\ScheduleException;
use Illuminate\Http\RedirectResponse;

class ScheduleExceptionController extends Controller
{
    public function store(ScheduleExceptionRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->scheduleExceptions()->create($request->validated());

        return redirect()->route('clients.schedule.index', $client);
    }

    public function destroy(Client $client, ScheduleException $scheduleException): RedirectResponse
    {
        $this->authorize('view', $client);

        $scheduleException->delete();

        return redirect()->route('clients.schedule.index', $client);
    }
}
