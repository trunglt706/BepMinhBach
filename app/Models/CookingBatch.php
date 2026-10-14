<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Support\PostImageProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        'starts_on',
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
            'cover_widths' => 'array',
            'cover_width' => 'integer',
            'cover_height' => 'integer',
        ];
    }

    /**
     * Ngày bắt đầu (kiểu DATE, không có giờ). Luôn lưu dạng "Y-m-d" để so sánh khoảng ngày đúng trên mọi CSDL
     * (cast "date" mặc định của Laravel ghi kèm "00:00:00", sai khi so chuỗi trên SQLite); đọc ra Carbon 00:00.
     *
     * @return Attribute<Carbon|null, mixed>
     */
    protected function startsOn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn (mixed $value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /**
     * Xóa đợt → xóa file ảnh nền sau khi giao dịch thành công.
     */
    protected static function booted(): void
    {
        // Mã công khai cho URL trang đợt (/dot-nau/{public_id}): ngẫu nhiên, duy nhất, không suy ra được từ ID.
        static::creating(function (CookingBatch $batch) {
            while (! $batch->public_id) {
                $candidate = bin2hex(random_bytes(8));
                if (! static::where('public_id', $candidate)->exists()) {
                    $batch->public_id = $candidate;
                }
            }
        });

        static::deleted(function (CookingBatch $batch) {
            if ($batch->cover_path) {
                $path = $batch->cover_path;
                $widths = $batch->cover_widths ?? [];
                DB::afterCommit(fn () => app(PostImageProcessor::class)->delete($path, $widths));
            }
        });
    }

    /**
     * Ảnh nền dưới dạng PostImage (không lưu DB) để dùng chung url()/srcset()/x-post-img với ảnh bài viết.
     */
    public function coverImage(): ?PostImage
    {
        if (! $this->cover_path || ! $this->cover_widths) {
            return null;
        }

        return (new PostImage)->forceFill([
            'kind' => PostImage::COVER,
            'path' => $this->cover_path,
            'widths' => $this->cover_widths,
            'width' => $this->cover_width,
            'height' => $this->cover_height,
        ]);
    }

    /**
     * Đợt khách được xem ở trang công khai: đang diễn ra, hoặc có bài viết đã đăng.
     * Đợt nháp/kế hoạch chưa có bài không lộ ra ngoài (không dò được qua ID).
     *
     * @param  Builder<CookingBatch>  $query
     * @return Builder<CookingBatch>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', BatchStatus::InProgress->value)
            ->orWhereHas('posts', fn (Builder $p) => $p->published()));
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
     * Kênh thông báo (Slack/Telegram/Chatwork) của đợt.
     *
     * @return HasMany<BatchNotificationChannel, $this>
     */
    public function notificationChannels(): HasMany
    {
        return $this->hasMany(BatchNotificationChannel::class);
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
