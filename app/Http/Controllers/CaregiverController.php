<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaregiverRequest;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CaregiverController extends Controller
{
    public function index(Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load('caregivers');

        return Inertia::render('caregivers/index', [
            'client' => $client,
            'caregivers' => $client->caregivers,
        ]);
    }

    public function create(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('caregivers/create', [
            'client' => $client,
        ]);
    }

    public function store(CaregiverRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->caregivers()->create($request->validated());

        return redirect()->route('clients.caregivers.index', $client);
    }

    public function edit(Client $client, Caregiver $caregiver): Response
    {
        $this->authorize('view', $caregiver);

        return Inertia::render('caregivers/edit', [
            'client' => $client,
            'caregiver' => $caregiver,
        ]);
    }

    public function update(CaregiverRequest $request, Client $client, Caregiver $caregiver): RedirectResponse
    {
        $this->authorize('update', $caregiver);

        $caregiver->update($request->validated());

        return redirect()->route('clients.caregivers.index', $client);
    }

    public function destroy(Client $client, Caregiver $caregiver): RedirectResponse
    {
        $this->authorize('delete', $caregiver);

        $caregiver->delete();

        return redirect()->route('clients.caregivers.index', $client);
    }
}
