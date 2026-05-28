<?php

namespace App\Models;

use App\Enums\OpenSwapRequestStatus;
use Database\Factories\OpenSwapRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenSwapRequest extends Model
{
    /** @use HasFactory<OpenSwapRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'schedule_exception_id',
        'date',
        'requester_caregiver_id',
        'status',
        'selected_offer_id',
        'fulfilled_at',
        'resulting_exception_ids',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fulfilled_at' => 'datetime',
            'status' => OpenSwapRequestStatus::class,
            'resulting_exception_ids' => 'array',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'requester_caregiver_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(OpenSwapOffer::class);
    }

    public function selectedOffer(): BelongsTo
    {
        return $this->belongsTo(OpenSwapOffer::class, 'selected_offer_id');
    }
}
