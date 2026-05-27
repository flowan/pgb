<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetCategoryRequest;
use App\Models\BudgetCategory;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BudgetCategoryController extends Controller
{
    public function index(Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load(['budgetCategories.expenses.caregiver', 'caregivers']);

        return Inertia::render('budget/index', [
            'categories' => $client->budgetCategories,
            'caregivers' => $client->caregivers,
            'client' => $client,
        ]);
    }

    public function store(BudgetCategoryRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->budgetCategories()->create($request->validated());

        return redirect()->route('clients.budget.index', $client);
    }

    public function update(BudgetCategoryRequest $request, Client $client, BudgetCategory $budgetCategory): RedirectResponse
    {
        $this->authorize('update', $budgetCategory);

        $budgetCategory->update($request->validated());

        return redirect()->route('clients.budget.index', $client);
    }

    public function destroy(Client $client, BudgetCategory $budgetCategory): RedirectResponse
    {
        $this->authorize('delete', $budgetCategory);

        $budgetCategory->delete();

        return redirect()->route('clients.budget.index', $client);
    }
}
