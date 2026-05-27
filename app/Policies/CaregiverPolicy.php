<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\User;

class CaregiverPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Caregiver $caregiver): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $caregiver->client->budget_holder_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Caregiver $caregiver): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $caregiver->client->budget_holder_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Caregiver $caregiver): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $caregiver->client->budget_holder_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Caregiver $caregiver): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $caregiver->client->budget_holder_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Caregiver $caregiver): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $caregiver->client->budget_holder_id === $user->id;
    }
}
