<?php

namespace App\Models;

use App\Enums\ShiftSwapRequestStatus;
use Database\Factories\ShiftSwapRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequest extends Model
{
    /** @use HasFactory<ShiftSwapRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'requester_caregiver_id',
        'requester_schedule_id',
        'requester_schedule_exception_id',
        'requester_date',
        'target_caregiver_id',
        'target_schedule_id',
        'target_schedule_exception_id',
        'target_date',
        'status',
        'responded_at',
        'decline_reason',
        'resulting_exception_ids',
    ];

    protected function casts(): array
    {
        return [
            'requester_date' => 'date',
            'target_date' => 'date',
            'responded_at' => 'datetime',
            'status' => ShiftSwapRequestStatus::class,
            'resulting_exception_ids' => 'array',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'requester_caregiver_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'target_caregiver_id');
    }

    public function requesterSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'requester_schedule_id');
    }

    public function requesterScheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'requester_schedule_exception_id');
    }

    public function targetSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'target_schedule_id');
    }

    public function targetScheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'target_schedule_exception_id');
    }
}
