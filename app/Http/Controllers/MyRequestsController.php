<?php

namespace App\Http\Controllers;

use App\Models\Caregiver;
use App\Models\OpenSwapRequest;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;
use App\Models\ShiftTakeoverRequest;
use Inertia\Inertia;
use Inertia\Response;

class MyRequestsController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $caregiverIds = Caregiver::where('user_id', $user->id)->pluck('id');

        return Inertia::render('my-requests/index', [
            'myTakeoverOffers' => ShiftTakeoverOffer::whereIn('offered_by_caregiver_id', $caregiverIds)
                ->with(['schedule.client', 'scheduleException.client', 'claimedBy'])
                ->latest()->get(),
            'myDirectSwapsOut' => ShiftSwapRequest::whereIn('requester_caregiver_id', $caregiverIds)
                ->with(['requester', 'target', 'requesterSchedule.client', 'targetSchedule.client'])
                ->latest()->get(),
            'directSwapsIn' => ShiftSwapRequest::whereIn('target_caregiver_id', $caregiverIds)
                ->where('status', 'pending')
                ->with(['requester', 'requesterSchedule.client', 'targetSchedule.client'])
                ->latest()->get(),
            'myOpenSwaps' => OpenSwapRequest::whereIn('requester_caregiver_id', $caregiverIds)
                ->with(['schedule.client', 'scheduleException.client', 'offers.offeredBy'])
                ->latest()->get(),
            'openSwapsToBidOn' => OpenSwapRequest::whereHas('schedule.client.caregivers', fn ($q) => $q->where('user_id', $user->id))
                ->where('status', 'open')
                ->whereNotIn('requester_caregiver_id', $caregiverIds)
                ->with(['requester', 'schedule.client', 'scheduleException.client'])
                ->latest()->get(),
            'myTakeoverRequestsOut' => ShiftTakeoverRequest::whereIn('requester_caregiver_id', $caregiverIds)
                ->with(['requester', 'target', 'targetSchedule.client', 'targetScheduleException.client'])
                ->latest()->get(),
            'takeoverRequestsIn' => ShiftTakeoverRequest::whereIn('target_caregiver_id', $caregiverIds)
                ->where('status', 'pending')
                ->with(['requester', 'targetSchedule.client', 'targetScheduleException.client'])
                ->latest()->get(),
        ]);
    }
}
