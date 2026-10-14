<?php

namespace App\Models;

use App\Enums\NotificationDriver;
use Database\Factories\BatchNotificationChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kênh thông báo của một đợt nấu. Mỗi đợt có tối đa một kênh cho mỗi loại (Slack/Telegram/Chatwork).
 *
 * @property NotificationDriver $driver
 * @property array<string, string> $settings thông số kết nối (đã giải mã)
 */
class BatchNotificationChannel extends Model
{
    /** @use HasFactory<BatchNotificationChannelFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['driver', 'settings', 'join_url', 'is_enabled'];

    /** @var list<string> token/webhook không bao giờ ra JSON/mảng */
    protected $hidden = ['settings'];

    /**
     * Ép kiểu thuộc tính. settings mã hóa bằng APP_KEY (đổi APP_KEY thì phải nhập lại token).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => NotificationDriver::class,
            'settings' => 'encrypted:array',
            'is_enabled' => 'boolean',
            'last_sent_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    /**
     * Đợt nấu của kênh.
     *
     * @return BelongsTo<CookingBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    /**
     * Một thông số đã lưu.
     *
     * @param  string  $key  tên thông số
     */
    public function setting(string $key): ?string
    {
        $value = $this->settings[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * Giá trị để hiện trong form/trang: thông số bí mật chỉ hiện 4 ký tự cuối.
     *
     * @param  string  $key  tên thông số
     */
    public function displaySetting(string $key): ?string
    {
        $value = $this->setting($key);
        if ($value === null) {
            return null;
        }

        return ($this->driver->fields()[$key]['secret'] ?? false) ? '••••'.mb_substr($value, -4) : $value;
    }

    /**
     * Kết quả gửi gần nhất bị lỗi (lỗi mới hơn lần gửi thành công cuối).
     */
    public function isFailing(): bool
    {
        return $this->last_failed_at !== null && ($this->last_sent_at === null || $this->last_failed_at->gt($this->last_sent_at));
    }
}
