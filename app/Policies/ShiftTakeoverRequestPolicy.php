<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShiftTakeoverRequest;
use App\Models\User;

class ShiftTakeoverRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Caregiver;
    }

    public function delete(User $user, ShiftTakeoverRequest $request): bool
    {
        return $request->requester->user_id === $user->id;
    }

    public function respond(User $user, ShiftTakeoverRequest $request): bool
    {
        return $request->target->user_id === $user->id;
    }
}
