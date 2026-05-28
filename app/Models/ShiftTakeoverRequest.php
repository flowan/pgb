<?php

namespace App\Models;

use App\Enums\ShiftTakeoverRequestStatus;
use Database\Factories\ShiftTakeoverRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTakeoverRequest extends Model
{
    /** @use HasFactory<ShiftTakeoverRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'requester_caregiver_id',
        'target_caregiver_id',
        'target_schedule_id',
        'target_schedule_exception_id',
        'target_date',
        'status',
        'responded_at',
        'decline_reason',
        'message',
        'resulting_exception_id',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'responded_at' => 'datetime',
            'status' => ShiftTakeoverRequestStatus::class,
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

    public function targetSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'target_schedule_id');
    }

    public function targetScheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'target_schedule_exception_id');
    }

    public function resultingException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'resulting_exception_id');
    }
}
