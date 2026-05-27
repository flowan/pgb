<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $clients = Client::where('budget_holder_id', auth()->id())
            ->withCount('caregivers')
            ->with('budgetCategories')
            ->get();

        return Inertia::render('clients/index', [
            'clients' => $clients,
        ]);
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);
        $client->load(['caregivers', 'budgetCategories']);

        return Inertia::render('clients/show', [
            'client' => $client,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/create');
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::create([
            ...$request->validated(),
            'budget_holder_id' => auth()->id(),
        ]);

        return redirect()->route('clients.index');
    }

    public function edit(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('clients/edit', [
            'client' => $client,
        ]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $client->update($request->validated());

        return redirect()->route('clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);
        $client->delete();

        return redirect()->route('clients.index');
    }
}
