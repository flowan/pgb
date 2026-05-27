<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetExpenseRequest;
use App\Models\BudgetExpense;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;

class BudgetExpenseController extends Controller
{
    public function store(BudgetExpenseRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $expense = BudgetExpense::create($request->validated());
        $expense->budgetCategory->updateSpentAmount();

        return redirect()->route('clients.budget.index', $client);
    }

    public function update(BudgetExpenseRequest $request, Client $client, BudgetExpense $budgetExpense): RedirectResponse
    {
        $this->authorize('update', $budgetExpense);

        $budgetExpense->update($request->validated());
        $budgetExpense->budgetCategory->updateSpentAmount();

        return redirect()->route('clients.budget.index', $client);
    }

    public function destroy(Client $client, BudgetExpense $budgetExpense): RedirectResponse
    {
        $this->authorize('delete', $budgetExpense);

        $category = $budgetExpense->budgetCategory;
        $budgetExpense->delete();
        $category->updateSpentAmount();

        return redirect()->route('clients.budget.index', $client);
    }
}
