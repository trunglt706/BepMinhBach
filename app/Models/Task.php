<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'status' => 'todo',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->due_at !== null && $this->due_at->isPast();
    }

    /** Cập nhật trạng thái, ghi người/thời điểm; ghi nhận hoàn thành khi chuyển sang done. */
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
