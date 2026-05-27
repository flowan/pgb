<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\User;

class AvailabilitySlotPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AvailabilitySlot $availabilitySlot): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $availabilitySlot->client->budget_holder_id === $user->id;
    }
}
