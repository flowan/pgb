<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilitySlotRequest;
use App\Models\AvailabilitySlot;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;

class AvailabilitySlotController extends Controller
{
    public function store(AvailabilitySlotRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);
        $this->authorize('create', AvailabilitySlot::class);

        $data = [
            'client_id' => $client->id,
            'start_time' => $request->validated('start_time'),
            'end_time' => $request->validated('end_time'),
            'notes' => $request->validated('notes'),
        ];

        if ($request->validated('type') === 'recurring') {
            $data['day_of_week'] = $request->validated('day_of_week');
        } else {
            $data['date'] = $request->validated('date');
        }

        $client->availabilitySlots()->create($data);

        return redirect()->route('clients.schedule.index', $client);
    }

    public function destroy(Client $client, AvailabilitySlot $availabilitySlot): RedirectResponse
    {
        $this->authorize('delete', $availabilitySlot);

        if ($availabilitySlot->schedule_id) {
            $availabilitySlot->schedule()->delete();
        }

        if ($availabilitySlot->schedule_exception_id) {
            $availabilitySlot->scheduleException()->delete();
        }

        $availabilitySlot->delete();

        return redirect()->route('clients.schedule.index', $client);
    }
}
