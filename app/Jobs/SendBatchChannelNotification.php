<?php

namespace App\Jobs;

use App\Models\BatchNotificationChannel;
use App\Support\BatchNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gửi một thông báo của chương trình vào MỘT kênh (Slack/Telegram/Chatwork), chạy nền qua queue.
 *
 * - Mỗi kênh một job: kênh chậm/lỗi không làm trễ kênh khác; thao tác của người dùng không chờ mạng.
 * - Payload chỉ chứa ID kênh + nội dung đã lọc — KHÔNG chứa token (bảng jobs không giữ bí mật).
 *   Token được đọc lại từ DB lúc gửi; kênh đã tắt/xóa thì bỏ qua.
 * - Lỗi (mạng, token sai…) đã được BatchNotifier ghi vào kênh (last_error); thử lại tối đa 3 lần
 *   (sau 30 giây, rồi 2 phút) phòng lỗi mạng tạm thời.
 */
class SendBatchChannelNotification implements ShouldQueue
{
    use Queueable;

    /** Số lần thử tối đa. */
    public int $tries = 3;

    /** Thời gian tối đa một lần chạy (giây) — mỗi yêu cầu HTTP đã giới hạn 5 giây. */
    public int $timeout = 30;

    /**
     * @param  int  $channelId  ID kênh thông báo
     * @param  string  $text  nội dung sự kiện (đã lọc thông tin nhạy cảm)
     * @param  string|null  $actor  tên người thực hiện
     */
    public function __construct(
        public int $channelId,
        public string $text,
        public ?string $actor,
    ) {}

    /**
     * Gửi tin; lỗi thì hẹn thử lại (không ném lỗi ra worker).
     *
     * @param  BatchNotifier  $notifier  dịch vụ gửi
     */
    public function handle(BatchNotifier $notifier): void
    {
        $channel = BatchNotificationChannel::with('batch')->find($this->channelId);
        if (! $channel || ! $channel->is_enabled || ! $channel->batch) {
            return;
        }

        $error = $notifier->send($channel, $channel->batch, $this->text, $this->actor);
        if ($error !== null && $this->attempts() < $this->tries) {
            $this->release($this->attempts() === 1 ? 30 : 120);
        }
    }
}
