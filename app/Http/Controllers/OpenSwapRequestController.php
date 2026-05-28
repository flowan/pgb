<?php

namespace App\Http\Controllers;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Http\Requests\OpenSwapRequestRequest;
use App\Models\Caregiver;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Services\ShiftReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class OpenSwapRequestController extends Controller
{
    public function __construct(private ShiftReassignmentService $reassignmentService) {}

    public function store(OpenSwapRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', OpenSwapRequest::class);

        $scheduleId = $request->validated('schedule_id');
        $scheduleExceptionId = $request->validated('schedule_exception_id');

        if ($scheduleId) {
            $source = Schedule::findOrFail($scheduleId);
        } else {
            $source = ScheduleException::findOrFail($scheduleExceptionId);
        }

        $caregiver = Caregiver::where('user_id', auth()->id())
            ->where('client_id', $source->client_id)
            ->first();

        if (! $caregiver) {
            abort(403);
        }

        OpenSwapRequest::create([
            'schedule_id' => $scheduleId,
            'schedule_exception_id' => $scheduleExceptionId,
            'date' => $request->validated('date'),
            'requester_caregiver_id' => $caregiver->id,
            'status' => OpenSwapRequestStatus::Open,
            'notes' => $request->validated('notes'),
        ]);

        // notify in Task 8

        return back();
    }

    public function destroy(OpenSwapRequest $openSwapRequest): RedirectResponse
    {
        $this->authorize('delete', $openSwapRequest);

        if ($openSwapRequest->status !== OpenSwapRequestStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'Only open swap requests can be cancelled.',
            ]);
        }

        $openSwapRequest->update(['status' => OpenSwapRequestStatus::Cancelled]);

        // notify in Task 8

        return back();
    }

    public function acceptOffer(OpenSwapRequest $openSwapRequest, OpenSwapOffer $offer): RedirectResponse
    {
        $this->authorize('acceptOffer', $openSwapRequest);

        if ($openSwapRequest->status !== OpenSwapRequestStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'This swap request is no longer open.',
            ]);
        }

        if ($offer->open_swap_request_id !== $openSwapRequest->id) {
            abort(404);
        }

        $requesterSource = $openSwapRequest->schedule_id
            ? $openSwapRequest->schedule
            : $openSwapRequest->scheduleException;

        $offerSource = $offer->offered_schedule_id
            ? $offer->offeredSchedule
            : $offer->offeredScheduleException;

        if (! $requesterSource || ! $offerSource) {
            abort(404);
        }

        // offering caregiver takes the requester's shift
        $exceptionForRequesterShift = $this->reassignmentService->reassign(
            $requesterSource,
            $openSwapRequest->date->toDateString(),
            $offer->offered_by_caregiver_id,
        );

        // requester takes the offering caregiver's shift
        $exceptionForOfferShift = $this->reassignmentService->reassign(
            $offerSource,
            $offer->offered_date->toDateString(),
            $openSwapRequest->requester_caregiver_id,
        );

        $openSwapRequest->update([
            'status' => OpenSwapRequestStatus::Fulfilled,
            'selected_offer_id' => $offer->id,
            'fulfilled_at' => now(),
            'resulting_exception_ids' => [
                $exceptionForRequesterShift->id,
                $exceptionForOfferShift->id,
            ],
        ]);

        $offer->update(['status' => OpenSwapOfferStatus::Accepted]);

        OpenSwapOffer::where('open_swap_request_id', $openSwapRequest->id)
            ->where('id', '!=', $offer->id)
            ->where('status', OpenSwapOfferStatus::Pending)
            ->update(['status' => OpenSwapOfferStatus::Declined]);

        // notify in Task 8

        return back();
    }
}
