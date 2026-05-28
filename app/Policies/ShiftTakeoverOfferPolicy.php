<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\ShiftTakeoverOffer;
use App\Models\User;

class ShiftTakeoverOfferPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Caregiver;
    }

    public function delete(User $user, ShiftTakeoverOffer $offer): bool
    {
        return $offer->offeredBy && $offer->offeredBy->user_id === $user->id;
    }

    public function claim(User $user, ShiftTakeoverOffer $offer): bool
    {
        if ($user->role !== UserRole::Caregiver) {
            return false;
        }

        $clientId = $this->resolveClientId($offer);

        if ($clientId === null) {
            return false;
        }

        $caregiver = Caregiver::where('user_id', $user->id)
            ->where('client_id', $clientId)
            ->first();

        if (! $caregiver) {
            return false;
        }

        return $caregiver->id !== $offer->offered_by_caregiver_id;
    }

    private function resolveClientId(ShiftTakeoverOffer $offer): ?int
    {
        if ($offer->schedule_id) {
            return $offer->schedule?->client_id;
        }

        if ($offer->schedule_exception_id) {
            return $offer->scheduleException?->client_id;
        }

        return null;
    }
}
