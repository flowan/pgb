<?php

namespace App\Models;

use Database\Factories\BudgetCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategory extends Model
{
    /** @use HasFactory<BudgetCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'allocated_amount',
        'spent_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BudgetExpense::class);
    }

    public function updateSpentAmount(): void
    {
        $this->update([
            'spent_amount' => $this->expenses()->sum('amount'),
        ]);
    }
}
