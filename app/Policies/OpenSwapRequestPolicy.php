<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\OpenSwapRequest;
use App\Models\User;

class OpenSwapRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Caregiver;
    }

    public function delete(User $user, OpenSwapRequest $request): bool
    {
        return $request->requester && $request->requester->user_id === $user->id;
    }

    public function acceptOffer(User $user, OpenSwapRequest $request): bool
    {
        return $this->delete($user, $request);
    }

    public function bid(User $user, OpenSwapRequest $request): bool
    {
        if ($user->role !== UserRole::Caregiver) {
            return false;
        }

        $clientId = $this->resolveClientId($request);

        if ($clientId === null) {
            return false;
        }

        $caregiver = Caregiver::where('user_id', $user->id)
            ->where('client_id', $clientId)
            ->first();

        if (! $caregiver) {
            return false;
        }

        return $caregiver->id !== $request->requester_caregiver_id;
    }

    private function resolveClientId(OpenSwapRequest $request): ?int
    {
        if ($request->schedule_id) {
            return $request->schedule?->client_id;
        }

        if ($request->schedule_exception_id) {
            return $request->scheduleException?->client_id;
        }

        return null;
    }
}
