<?php

namespace App\Models;

use App\Enums\CaregiverType;
use Database\Factories\CaregiverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caregiver extends Model
{
    /** @use HasFactory<CaregiverFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'name',
        'type',
        'hourly_rate',
    ];

    protected function casts(): array
    {
        return [
            'type' => CaregiverType::class,
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function budgetExpenses(): HasMany
    {
        return $this->hasMany(BudgetExpense::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }
}
