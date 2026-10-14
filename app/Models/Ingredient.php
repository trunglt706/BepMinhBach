<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingredient extends Model
{
    use HasFactory;

    /** Đơn vị gợi ý; vẫn cho phép đơn vị tự nhập ngắn. Không tự quy đổi đơn vị. */
    public const UNITS = ['kg', 'g', 'lít', 'ml', 'bó', 'gói', 'thùng', 'cái', 'quả', 'chai', 'hộp'];

    protected $fillable = [
        'name',
        'expected_quantity',
        'actual_quantity',
        'unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'needs_restock' => 'boolean',
            'restock_reported_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    public function restockReporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restock_reported_by');
    }
}
