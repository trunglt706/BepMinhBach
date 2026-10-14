<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CookingBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'starts_at',
        'location_text',
        'expected_servings',
        'estimated_cost_vnd',
        'coordinator_id',
        'notes',
    ];

    protected $attributes = [
        'status' => 'draft',
        'results_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'status' => BatchStatus::class,
            'expected_servings' => 'integer',
            'prepared_servings' => 'integer',
            'distributed_servings' => 'integer',
            'estimated_cost_vnd' => 'integer',
            'results_updated_at' => 'datetime',
            'results_version' => 'integer',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resultsUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'results_updated_by');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /** Đã phát sinh dữ liệu: không được xóa cứng, chỉ được hủy. */
    public function hasHistory(): bool
    {
        return $this->status !== BatchStatus::Draft
            || $this->prepared_servings !== null
            || $this->distributed_servings !== null
            || $this->ingredients()->exists()
            || $this->tasks()->exists()
            || $this->expenseItems()->exists();
    }

    /**
     * Nhắc kiểm tra chênh lệch giữa số dự kiến/chuẩn bị/phân phát.
     * Chỉ để rà soát — không tự sửa số liệu cho khớp.
     *
     * @return list<array{level: string, message: string}>
     */
    public function servingVariances(): array
    {
        $fmt = fn (int $v) => number_format($v, 0, ',', '.');
        $notes = [];

        if ($this->prepared_servings !== null && $this->prepared_servings !== $this->expected_servings) {
            $diff = $this->prepared_servings - $this->expected_servings;
            $notes[] = [
                'level' => 'info',
                'message' => 'Số suất đã chuẩn bị '.($diff > 0 ? 'nhiều hơn' : 'ít hơn').' dự kiến '.$fmt(abs($diff)).' suất.',
            ];
        }

        if ($this->prepared_servings !== null && $this->distributed_servings !== null) {
            $diff = $this->distributed_servings - $this->prepared_servings;
            if ($diff > 0) {
                $notes[] = [
                    'level' => 'warning',
                    'message' => 'Số suất đã phân phát nhiều hơn số đã chuẩn bị '.$fmt($diff).' suất. Vui lòng kiểm tra lại.',
                ];
            } elseif ($diff < 0) {
                $notes[] = [
                    'level' => 'info',
                    'message' => 'Còn '.$fmt(abs($diff)).' suất đã chuẩn bị nhưng chưa ghi nhận phân phát.',
                ];
            }
        }

        if ($this->prepared_servings === null && $this->distributed_servings !== null) {
            $notes[] = ['level' => 'warning', 'message' => 'Đã nhập số suất phân phát nhưng chưa nhập số suất đã chuẩn bị.'];
        }

        return $notes;
    }

    /** Thêm tasks_count và tasks_done_count cho thanh tiến độ. */
    public function scopeWithTaskProgress(Builder $query): Builder
    {
        return $query->withCount([
            'tasks',
            'tasks as tasks_done_count' => fn (Builder $q) => $q->where('status', TaskStatus::Done->value),
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BatchStatus::Draft->value,
            BatchStatus::Planned->value,
            BatchStatus::InProgress->value,
        ]);
    }
}
