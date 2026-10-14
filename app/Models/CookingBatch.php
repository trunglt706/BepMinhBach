<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Đợt nấu ăn: kế hoạch, nguyên liệu, công việc, số suất thực tế, chi phí.
 *
 * @property BatchStatus $status
 */
class CookingBatch extends Model
{
    use HasFactory;

    /** Mức nhắc chênh lệch số suất: thông tin (lớp CSS callout-info). */
    public const VARIANCE_INFO = 'info';

    /** Mức nhắc chênh lệch số suất: cần kiểm tra (lớp CSS callout-warning). */
    public const VARIANCE_WARNING = 'warning';

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
        'status' => BatchStatus::Draft->value,
        'results_version' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'reopened_at' => 'datetime',
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

    /**
     * Người điều phối đợt.
     *
     * @return BelongsTo<User, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    /**
     * Người tạo đợt.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Người cập nhật số suất lần cuối.
     *
     * @return BelongsTo<User, $this>
     */
    public function resultsUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'results_updated_by');
    }

    /**
     * Nguyên liệu của đợt.
     *
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    /**
     * Công việc (checklist) của đợt.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Khoản chi của đợt.
     *
     * @return HasMany<ExpenseItem, $this>
     */
    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    /**
     * Bài viết công khai về đợt này (gồm cả bản nháp).
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'cooking_batch_id');
    }

    /**
     * Lịch sử thay đổi của đợt.
     *
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Đợt đã khóa (đã hoàn tất hoặc đã hủy).
     */
    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /**
     * Đã phát sinh dữ liệu: không được xóa cứng, chỉ được hủy.
     */
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
     * @return list<array{level: string, message: string}> level là self::VARIANCE_*
     */
    public function servingVariances(): array
    {
        $fmt = fn (int $v) => number_format($v, 0, ',', '.');
        $notes = [];

        if ($this->prepared_servings !== null && $this->prepared_servings !== $this->expected_servings) {
            $diff = $this->prepared_servings - $this->expected_servings;
            $notes[] = [
                'level' => self::VARIANCE_INFO,
                'message' => 'Số suất đã chuẩn bị '.($diff > 0 ? 'nhiều hơn' : 'ít hơn').' dự kiến '.$fmt(abs($diff)).' suất.',
            ];
        }

        if ($this->prepared_servings !== null && $this->distributed_servings !== null) {
            $diff = $this->distributed_servings - $this->prepared_servings;
            if ($diff > 0) {
                $notes[] = [
                    'level' => self::VARIANCE_WARNING,
                    'message' => 'Số suất đã phân phát nhiều hơn số đã chuẩn bị '.$fmt($diff).' suất. Vui lòng kiểm tra lại.',
                ];
            } elseif ($diff < 0) {
                $notes[] = [
                    'level' => self::VARIANCE_INFO,
                    'message' => 'Còn '.$fmt(abs($diff)).' suất đã chuẩn bị nhưng chưa ghi nhận phân phát.',
                ];
            }
        }

        if ($this->prepared_servings === null && $this->distributed_servings !== null) {
            $notes[] = ['level' => self::VARIANCE_WARNING, 'message' => 'Đã nhập số suất phân phát nhưng chưa nhập số suất đã chuẩn bị.'];
        }

        return $notes;
    }

    /**
     * Thêm tasks_count và tasks_done_count cho thanh tiến độ.
     *
     * @param  Builder<CookingBatch>  $query
     * @return Builder<CookingBatch>
     */
    public function scopeWithTaskProgress(Builder $query): Builder
    {
        return $query->withCount([
            'tasks',
            'tasks as tasks_done_count' => fn (Builder $q) => $q->where('status', TaskStatus::Done->value),
        ]);
    }

    /**
     * Đợt đang mở (mọi trạng thái chưa khóa: nháp, đã lên kế hoạch, đang diễn ra).
     *
     * @param  Builder<CookingBatch>  $query
     * @return Builder<CookingBatch>
     */
    public function scopeActive(Builder $query): Builder
    {
        $open = array_filter(BatchStatus::cases(), fn (BatchStatus $s) => ! $s->isLocked());

        return $query->whereIn('status', array_map(fn (BatchStatus $s) => $s->value, $open));
    }
}
