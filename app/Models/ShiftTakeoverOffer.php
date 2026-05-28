<?php

namespace App\Models;

use App\Enums\ShiftTakeoverOfferStatus;
use Database\Factories\ShiftTakeoverOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTakeoverOffer extends Model
{
    /** @use HasFactory<ShiftTakeoverOfferFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'schedule_exception_id',
        'date',
        'offered_by_caregiver_id',
        'status',
        'claimed_by_caregiver_id',
        'claimed_at',
        'resulting_exception_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'claimed_at' => 'datetime',
            'status' => ShiftTakeoverOfferStatus::class,
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class);
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'offered_by_caregiver_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'claimed_by_caregiver_id');
    }

    public function resultingException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'resulting_exception_id');
    }
}
