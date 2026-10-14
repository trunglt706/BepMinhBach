<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'cooking_batch_id',
        'user_id',
        'action',
        'summary',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    /** Ghi lịch sử với tóm tắt an toàn (không chứa mật khẩu/thông tin người nhận). */
    public static function record(?CookingBatch $batch, ?User $user, string $action, string $summary): self
    {
        return self::create([
            'cooking_batch_id' => $batch?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
        ]);
    }
}
