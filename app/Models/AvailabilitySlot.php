<?php

namespace App\Models;

use App\Enums\AvailabilitySlotStatus;
use Database\Factories\AvailabilitySlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilitySlot extends Model
{
    /** @use HasFactory<AvailabilitySlotFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'day_of_week',
        'date',
        'start_time',
        'end_time',
        'status',
        'claimed_by',
        'claimed_at',
        'schedule_id',
        'schedule_exception_id',
        'claimed_dates',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'claimed_at' => 'datetime',
            'status' => AvailabilitySlotStatus::class,
            'claimed_dates' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'claimed_by');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class);
    }
}
