<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nguyên liệu dự kiến/thực tế của một đợt nấu, có thể đang bị báo thiếu. */
class Ingredient extends Model
{
    use HasFactory;

    /** Đơn vị gợi ý; vẫn cho phép đơn vị tự nhập ngắn. Không tự quy đổi đơn vị. */
    public const UNITS = ['kg', 'g', 'lít', 'ml', 'bó', 'gói', 'thùng', 'cái', 'quả', 'chai', 'hộp'];

    /** Giá trị ô chọn đơn vị nghĩa là "Khác (tự nhập)" — đơn vị thật lấy từ ô unit_other. */
    public const UNIT_OTHER = 'other';

    /** Đơn vị chọn sẵn khi thêm nguyên liệu mới. */
    public const DEFAULT_UNIT = 'kg';

    protected $fillable = [
        'name',
        'expected_quantity',
        'actual_quantity',
        'unit',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'needs_restock' => 'boolean',
            'restock_reported_at' => 'datetime',
        ];
    }

    /**
     * Đợt nấu của nguyên liệu.
     *
     * @return BelongsTo<CookingBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    /**
     * Người báo thiếu gần nhất.
     *
     * @return BelongsTo<User, $this>
     */
    public function restockReporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restock_reported_by');
    }
}
