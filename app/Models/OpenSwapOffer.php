<?php

namespace App\Models;

use App\Enums\OpenSwapOfferStatus;
use Database\Factories\OpenSwapOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenSwapOffer extends Model
{
    /** @use HasFactory<OpenSwapOfferFactory> */
    use HasFactory;

    protected $fillable = [
        'open_swap_request_id',
        'offered_by_caregiver_id',
        'offered_schedule_id',
        'offered_schedule_exception_id',
        'offered_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'offered_date' => 'date',
            'status' => OpenSwapOfferStatus::class,
        ];
    }

    public function openSwapRequest(): BelongsTo
    {
        return $this->belongsTo(OpenSwapRequest::class);
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'offered_by_caregiver_id');
    }

    public function offeredSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'offered_schedule_id');
    }

    public function offeredScheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'offered_schedule_exception_id');
    }
}
