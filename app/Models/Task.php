<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Công việc trong checklist của một chương trình.
 *
 * @property TaskStatus $status
 */
class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'assignee_id',
        'due_at',
        'notes',
    ];

    protected $attributes = [
        'status' => TaskStatus::Todo->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }

    /**
     * Chương trình chứa công việc.
     *
     * @return BelongsTo<CookingBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    /**
     * Người được giao (có thể chưa giao).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Người đánh dấu hoàn thành.
     *
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Đã quá hạn mà chưa xong.
     */
    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Cập nhật trạng thái, ghi người/thời điểm; ghi nhận hoàn thành khi chuyển sang "Hoàn thành".
     * Lý do vướng chỉ giữ khi trạng thái là "Đang bị vướng". Không tự lưu (gọi save() sau).
     *
     * @param  TaskStatus  $status  trạng thái mới
     * @param  User  $user  người cập nhật
     * @param  string|null  $blockedNote  lý do bị vướng
     */
    public function applyStatus(TaskStatus $status, User $user, ?string $blockedNote = null): void
    {
        $this->status = $status;
        $this->status_updated_by = $user->id;
        $this->status_updated_at = now();
        $this->blocked_note = $status === TaskStatus::Blocked ? $blockedNote : null;

        if ($status === TaskStatus::Done) {
            $this->completed_at = now();
            $this->completed_by = $user->id;
        } else {
            $this->completed_at = null;
            $this->completed_by = null;
        }
    }
}
