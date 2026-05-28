<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShiftSwapRequest;
use App\Models\User;

class ShiftSwapRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Caregiver;
    }

    public function delete(User $user, ShiftSwapRequest $request): bool
    {
        return $request->requester && $request->requester->user_id === $user->id;
    }

    public function respond(User $user, ShiftSwapRequest $request): bool
    {
        return $request->target && $request->target->user_id === $user->id;
    }
}
