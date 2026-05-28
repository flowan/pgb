<?php

namespace App\Console\Commands;

use App\Enums\OpenSwapRequestStatus;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\OpenSwapRequest;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Console\Command;

class ExpireShiftRequests extends Command
{
    protected $signature = 'shift-requests:expire';

    protected $description = 'Expire shift takeover offers, swap requests, and open swap requests past their date';

    public function handle(): int
    {
        $today = now()->toDateString();

        $a = ShiftTakeoverOffer::where('status', ShiftTakeoverOfferStatus::Open)
            ->whereDate('date', '<', $today)
            ->update(['status' => ShiftTakeoverOfferStatus::Expired]);

        $b = ShiftSwapRequest::where('status', ShiftSwapRequestStatus::Pending)
            ->whereDate('requester_date', '<', $today)
            ->whereDate('target_date', '<', $today)
            ->update(['status' => ShiftSwapRequestStatus::Expired]);

        $c = OpenSwapRequest::where('status', OpenSwapRequestStatus::Open)
            ->whereDate('date', '<', $today)
            ->update(['status' => OpenSwapRequestStatus::Expired]);

        $this->info("Expired: $a takeovers, $b swaps, $c open swaps");

        return self::SUCCESS;
    }
}
