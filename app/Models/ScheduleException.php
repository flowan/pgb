<?php

namespace App\Models;

use App\Enums\ScheduleExceptionType;
use App\Observers\ScheduleExceptionObserver;
use Database\Factories\ScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ScheduleExceptionObserver::class)]
class ScheduleException extends Model
{
    /** @use HasFactory<ScheduleExceptionFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'client_id',
        'caregiver_id',
        'date',
        'start_time',
        'end_time',
        'type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => ScheduleExceptionType::class,
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function takeoverOffers(): HasMany
    {
        return $this->hasMany(ShiftTakeoverOffer::class);
    }
}
