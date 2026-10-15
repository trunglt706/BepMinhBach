<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Support\BatchNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lịch sử thay đổi quan trọng (nội bộ, chỉ người xem được tài chính thấy).
 *
 * Nguyên tắc dữ liệu: tóm tắt chỉ ghi SỐ HIỆU và giá trị nghiệp vụ (trạng thái, số suất, tên khoản chi…).
 * Không ghi mật khẩu, email, số điện thoại, ghi chú tự do (lý do vướng, lý do hủy/mở lại, ghi chú thiếu
 * nguyên liệu) hay bất kỳ thông tin nào về người nhận suất ăn.
 *
 * @property ActivityAction $action
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Độ dài tối đa của tóm tắt. */
    public const SUMMARY_MAX = 500;

    protected $fillable = [
        'cooking_batch_id',
        'user_id',
        'action',
        'summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
        ];
    }

    /**
     * Người thực hiện thao tác.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Chương trình liên quan (null với thao tác không thuộc chương trình nào, ví dụ quản lý tài khoản).
     *
     * @return BelongsTo<CookingBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    /**
     * Ghi một dòng lịch sử.
     *
     * Tóm tắt được che thêm một lớp (email, số điện thoại) phòng khi chuỗi ghép vô tình chứa dữ liệu cá nhân.
     *
     * @param  CookingBatch|null  $batch  chương trình liên quan
     * @param  User|null  $user  người thực hiện
     * @param  ActivityAction  $action  loại sự kiện
     * @param  string  $summary  mô tả ngắn, KHÔNG chứa dữ liệu nhạy cảm (xem ghi chú lớp)
     * @param  string|null  $notify  nội dung riêng cho kênh thông báo của chương trình (mặc định = tóm tắt). Truyền giá trị
     *                               cũng là yêu cầu gửi kể cả khi sự kiện không nằm trong BatchNotifier::EVENTS.
     */
    public static function record(?CookingBatch $batch, ?User $user, ActivityAction $action, string $summary, ?string $notify = null): self
    {
        $log = self::create([
            'cooking_batch_id' => $batch?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'summary' => mb_substr(self::redact($summary), 0, self::SUMMARY_MAX),
        ]);

        // Kênh thông báo của chương trình (Slack/Telegram/Chatwork): gửi sau khi lưu xong, không làm chậm thao tác.
        app(BatchNotifier::class)->queue($log, self::redact($notify ?? $summary), $user?->name, $notify !== null);

        return $log;
    }

    /**
     * Che email và số điện thoại Việt Nam (bắt đầu bằng 0 hoặc +84, 9–11 chữ số, cho phép dấu cách/chấm/gạch).
     * Không đụng số tiền ("1.250.000 đ") hay số suất vì chúng không bắt đầu bằng 0/+84.
     *
     * @param  string  $text  tóm tắt gốc
     * @return string tóm tắt đã che
     */
    public static function redact(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[email]', $text);

        return preg_replace('/(?<![\d.,])(?:\+84|0)(?:[ .-]?\d){8,10}(?![\d])/', '[số điện thoại]', $text);
    }
}
