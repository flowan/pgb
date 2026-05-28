<?php

namespace App\Http\Controllers;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Http\Requests\OpenSwapOfferRequest;
use App\Models\Caregiver;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class OpenSwapOfferController extends Controller
{
    public function store(OpenSwapOfferRequest $request, OpenSwapRequest $openSwapRequest): RedirectResponse
    {
        $this->authorize('bid', $openSwapRequest);

        if ($openSwapRequest->status !== OpenSwapRequestStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'This swap request is no longer open.',
            ]);
        }

        $requesterSource = $openSwapRequest->schedule_id
            ? $openSwapRequest->schedule
            : $openSwapRequest->scheduleException;

        if (! $requesterSource) {
            abort(404);
        }

        $caregiver = Caregiver::where('user_id', auth()->id())
            ->where('client_id', $requesterSource->client_id)
            ->first();

        if (! $caregiver) {
            abort(403);
        }

        OpenSwapOffer::create([
            'open_swap_request_id' => $openSwapRequest->id,
            'offered_by_caregiver_id' => $caregiver->id,
            'offered_schedule_id' => $request->validated('offered_schedule_id'),
            'offered_schedule_exception_id' => $request->validated('offered_schedule_exception_id'),
            'offered_date' => $request->validated('offered_date'),
            'status' => OpenSwapOfferStatus::Pending,
        ]);

        // notify in Task 8

        return back();
    }

    public function destroy(OpenSwapOffer $offer): RedirectResponse
    {
        $this->authorize('withdraw', $offer);

        if ($offer->status !== OpenSwapOfferStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending offers can be withdrawn.',
            ]);
        }

        $offer->update(['status' => OpenSwapOfferStatus::Withdrawn]);

        // notify in Task 8

        return back();
    }
}
