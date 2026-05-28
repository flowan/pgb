<?php

namespace App\Policies;

use App\Models\OpenSwapOffer;
use App\Models\User;

class OpenSwapOfferPolicy
{
    public function withdraw(User $user, OpenSwapOffer $offer): bool
    {
        return $offer->offeredBy && $offer->offeredBy->user_id === $user->id;
    }
}
