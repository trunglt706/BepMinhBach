<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount_vnd',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount_vnd' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
