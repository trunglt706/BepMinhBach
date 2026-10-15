<?php

namespace App\Support;

use App\Enums\ActivityAction;
use App\Enums\NotificationDriver;
use App\Jobs\SendBatchChannelNotification;
use App\Models\ActivityLog;
use App\Models\BatchNotificationChannel;
use App\Models\CookingBatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Đẩy thông báo của chương trình vào các kênh đã cấu hình (Slack, Telegram, Chatwork).
 *
 * - Nguồn sự kiện: ActivityLog::record() — mọi thay đổi quan trọng của chương trình đều đi qua đây.
 * - Chỉ gửi sự kiện trong EVENTS: mọi hoạt động của chương trình (thông tin, trạng thái, kết quả, nguyên liệu, công việc,
 *   bài viết công khai) — TRỪ chi phí và đơn giá nguyên liệu (tình nguyện viên không được xem chi phí, mà ai có
 *   link đều tham gia kênh được); không gửi chữ tự do (lý do vướng, ghi chú…) — tin nhắn có link về hệ thống.
 * - Gửi qua QUEUE (SendBatchChannelNotification, mỗi kênh đang bật một job) SAU khi giao dịch DB thành công:
 *   thao tác không chờ mạng; lỗi mạng/token sai không làm hỏng thao tác — chỉ ghi lỗi vào kênh (last_error).
 *   Production cần worker `php artisan queue:work` (dịch vụ gopgio-queue, cấu hình theo docs/DEPLOYMENT.md mục 0.1).
 * - Không gửi khi tạo chương trình (chương trình mới chưa có kênh) và khi xóa chương trình nháp (kênh bị xóa cùng chương trình).
 */
class BatchNotifier
{
    /** Sự kiện được gửi vào kênh. */
    public const EVENTS = [
        ActivityAction::BatchUpdated,
        ActivityAction::BatchStatusChanged,
        ActivityAction::BatchCompleted,
        ActivityAction::BatchCancelled,
        ActivityAction::BatchReopened,
        ActivityAction::BatchResultsUpdated,
        ActivityAction::IngredientCreated,
        ActivityAction::IngredientUpdated,
        ActivityAction::IngredientDeleted,
        ActivityAction::IngredientShortage,
        ActivityAction::IngredientResolved,
        ActivityAction::TaskCreated,
        ActivityAction::TaskUpdated,
        ActivityAction::TaskAssigned,
        ActivityAction::TaskStatusChanged,
        ActivityAction::TaskDeleted,
        ActivityAction::PostPublished,
        ActivityAction::PostUnpublished,
    ];

    /** Thời gian chờ mỗi lần gửi (giây). */
    private const TIMEOUT = 5;

    /**
     * Đưa thông báo của một dòng lịch sử vào queue (nếu là sự kiện được gửi và chương trình có kênh đang bật):
     * sau khi giao dịch DB thành công, mỗi kênh đang bật một job.
     *
     * @param  ActivityLog  $log  dòng lịch sử vừa ghi
     * @param  string  $text  nội dung sự kiện (đã lọc thông tin nhạy cảm)
     * @param  string|null  $actor  tên người thực hiện
     * @param  bool  $force  gửi kể cả khi sự kiện không nằm trong EVENTS (nơi ghi log chủ động soạn nội dung)
     */
    public function queue(ActivityLog $log, string $text, ?string $actor, bool $force = false): void
    {
        if (! $log->cooking_batch_id || (! $force && ! in_array($log->action, self::EVENTS, true))) {
            return;
        }

        $batchId = $log->cooking_batch_id;
        DB::afterCommit(function () use ($batchId, $text, $actor) {
            BatchNotificationChannel::where('cooking_batch_id', $batchId)->where('is_enabled', true)->pluck('id')
                ->each(fn (int $channelId) => SendBatchChannelNotification::dispatch($channelId, $text, $actor));
        });
    }

    /**
     * Gửi một tin vào một kênh và ghi kết quả (last_sent_at / last_error).
     *
     * @param  BatchNotificationChannel  $channel  kênh
     * @param  CookingBatch  $batch  chương trình
     * @param  string  $text  nội dung sự kiện
     * @param  string|null  $actor  tên người thực hiện
     * @return string|null null nếu thành công, ngược lại là mô tả lỗi (đã che token)
     */
    public function send(BatchNotificationChannel $channel, CookingBatch $batch, string $text, ?string $actor): ?string
    {
        try {
            $response = $this->request($channel, $this->message($batch, $text, $actor));
            $error = $this->errorFrom($channel, $response);
        } catch (ConnectionException) {
            $error = 'Không kết nối được tới '.$channel->driver->label().' (mạng hoặc hết thời gian chờ).';
        } catch (Throwable $e) {
            $error = $this->scrub($channel, 'Lỗi khi gửi: '.$e->getMessage());
        }

        $channel->forceFill($error === null
            ? ['last_sent_at' => now(), 'last_error' => null]
            : ['last_failed_at' => now(), 'last_error' => mb_substr($error, 0, 255)])->saveQuietly();

        if ($error !== null) {
            Log::warning('Gửi thông báo chương trình thất bại', ['channel_id' => $channel->id, 'driver' => $channel->driver->value, 'error' => $error]);
        }

        return $error;
    }

    /**
     * Nội dung tin: tiêu đề (tên + ngày chương trình), sự kiện, người thực hiện, link mở chương trình trong hệ thống.
     *
     * @param  CookingBatch  $batch  chương trình
     * @param  string  $text  nội dung sự kiện
     * @param  string|null  $actor  tên người thực hiện
     * @return array{title: string, text: string, url: string}
     */
    public function message(CookingBatch $batch, string $text, ?string $actor): array
    {
        return [
            'title' => 'Góp Gió · '.$batch->name.' ('.Format::date($batch->starts_on).')',
            'text' => $text.($actor ? ' — '.$actor : ''),
            'url' => route('batches.show', $batch),
        ];
    }

    /**
     * Gọi API của dịch vụ. Địa chỉ cố định theo từng dịch vụ (Slack: webhook đã kiểm tra là hooks.slack.com).
     *
     * @param  BatchNotificationChannel  $channel  kênh
     * @param  array{title: string, text: string, url: string}  $m  nội dung
     */
    private function request(BatchNotificationChannel $channel, array $m): Response
    {
        $http = Http::timeout(self::TIMEOUT)->connectTimeout(3)->acceptJson();

        return match ($channel->driver) {
            NotificationDriver::Slack => $http->post((string) $channel->setting('webhook_url'), [
                'text' => '*'.$this->slack($m['title'])."*\n".$this->slack($m['text'])."\n<".$m['url'].'|Mở chương trình>',
                'unfurl_links' => false,
            ]),
            NotificationDriver::Telegram => $http->post('https://api.telegram.org/bot'.$channel->setting('bot_token').'/sendMessage', [
                'chat_id' => $channel->setting('chat_id'),
                'text' => $m['title']."\n".$m['text']."\n".$m['url'],
                'disable_web_page_preview' => true,
            ]),
            NotificationDriver::Chatwork => $http->withHeaders(['X-ChatWorkToken' => (string) $channel->setting('api_token')])
                ->asForm()
                ->post('https://api.chatwork.com/v2/rooms/'.$channel->setting('room_id').'/messages', [
                    'body' => '[info][title]'.$this->chatwork($m['title']).'[/title]'.$this->chatwork($m['text'])."\n".$m['url'].'[/info]',
                ]),
        };
    }

    /**
     * Mô tả lỗi từ phản hồi (null nếu thành công). Telegram trả 200 kèm ok=false khi lỗi.
     *
     * @param  BatchNotificationChannel  $channel  kênh
     * @param  Response  $response  phản hồi
     */
    private function errorFrom(BatchNotificationChannel $channel, Response $response): ?string
    {
        $ok = $response->successful() && ($channel->driver !== NotificationDriver::Telegram || $response->json('ok') === true);
        if ($ok) {
            return null;
        }

        $detail = $response->json('description')                              // Telegram
            ?? (is_array($response->json('errors')) ? implode(', ', $response->json('errors')) : null) // Chatwork
            ?? trim(mb_substr($response->body(), 0, 120));                     // Slack: "invalid_token", "channel_not_found"…
        $hint = match ($response->status()) {
            401, 403 => ' — kiểm tra token/quyền của bot',
            404 => ' — kiểm tra webhook/chat ID/room ID',
            default => '',
        };

        return $this->scrub($channel, $channel->driver->label().' trả lỗi HTTP '.$response->status().($detail !== '' ? ': '.$detail : '').$hint);
    }

    /**
     * Che mọi thông số bí mật nếu lỡ xuất hiện trong thông báo lỗi.
     *
     * @param  BatchNotificationChannel  $channel  kênh
     * @param  string  $text  thông báo lỗi
     */
    private function scrub(BatchNotificationChannel $channel, string $text): string
    {
        foreach ($channel->driver->fields() as $key => $field) {
            $value = $channel->setting($key);
            if ($field['secret'] && $value !== null) {
                $text = str_replace($value, '***', $text);
            }
        }

        return $text;
    }

    /**
     * Thoát ký tự đặc biệt của Slack mrkdwn.
     *
     * @param  string  $text  chữ thường
     */
    private function slack(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    /**
     * Không cho chữ người dùng nhập tạo thẻ Chatwork ([To:…], [info]…): đổi ngoặc vuông sang ngoặc toàn chiều.
     *
     * @param  string  $text  chữ thường
     */
    private function chatwork(string $text): string
    {
        return str_replace(['[', ']'], ['［', '］'], $text);
    }
}
