<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Khoản chi nguyên liệu của một chương trình (số tiền VND nguyên, ghi nhận nội bộ). */
class ExpenseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount_vnd',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_vnd' => 'integer',
        ];
    }

    /**
     * Chương trình của khoản chi.
     *
     * @return BelongsTo<CookingBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    /**
     * Người ghi nhận khoản chi.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
