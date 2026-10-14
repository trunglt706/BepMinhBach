<?php

namespace App\Support;

use App\Enums\ActivityAction;
use App\Enums\NotificationDriver;
use App\Models\ActivityLog;
use App\Models\BatchNotificationChannel;
use App\Models\CookingBatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Đẩy thông báo của đợt nấu vào các kênh đã cấu hình (Slack, Telegram, Chatwork).
 *
 * - Nguồn sự kiện: ActivityLog::record() — mọi thay đổi quan trọng của đợt đều đi qua đây.
 * - Chỉ gửi sự kiện trong EVENTS: KHÔNG gửi chi phí (tình nguyện viên không được xem chi phí, mà ai có link
 *   đều tham gia kênh được), không gửi chữ tự do (lý do vướng, ghi chú thiếu…) — tin nhắn có link về hệ thống.
 * - Gửi SAU khi giao dịch DB thành công và SAU khi đã trả trang cho người dùng (defer): thao tác không bị chậm,
 *   lỗi mạng/token sai không làm hỏng thao tác — chỉ ghi lỗi vào kênh (last_error) để người quản lý thấy.
 * - Không cần queue worker. Mỗi lần gọi tối đa 5 giây/kênh.
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
        ActivityAction::IngredientShortage,
        ActivityAction::IngredientResolved,
        ActivityAction::TaskCreated,
        ActivityAction::TaskAssigned,
        ActivityAction::TaskStatusChanged,
        ActivityAction::PostPublished,
    ];

    /** Thời gian chờ mỗi lần gửi (giây). */
    private const TIMEOUT = 5;

    /**
     * Lên lịch gửi thông báo cho một dòng lịch sử (nếu là sự kiện được gửi và đợt có kênh đang bật).
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
        DB::afterCommit(fn () => defer(fn () => $this->deliver($batchId, $text, $actor)));
    }

    /**
     * Gửi tới mọi kênh đang bật của đợt. Không bao giờ ném lỗi.
     *
     * @param  int  $batchId  ID đợt nấu
     * @param  string  $text  nội dung sự kiện
     * @param  string|null  $actor  tên người thực hiện
     */
    public function deliver(int $batchId, string $text, ?string $actor): void
    {
        $batch = CookingBatch::find($batchId);
        if (! $batch) {
            return;
        }

        foreach ($batch->notificationChannels()->where('is_enabled', true)->get() as $channel) {
            $this->send($channel, $batch, $text, $actor);
        }
    }

    /**
     * Gửi một tin vào một kênh và ghi kết quả (last_sent_at / last_error).
     *
     * @param  BatchNotificationChannel  $channel  kênh
     * @param  CookingBatch  $batch  đợt nấu
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
            Log::warning('Gửi thông báo đợt nấu thất bại', ['channel_id' => $channel->id, 'driver' => $channel->driver->value, 'error' => $error]);
        }

        return $error;
    }

    /**
     * Nội dung tin: tiêu đề (tên + ngày đợt), sự kiện, người thực hiện, link mở đợt trong hệ thống.
     *
     * @param  CookingBatch  $batch  đợt nấu
     * @param  string  $text  nội dung sự kiện
     * @param  string|null  $actor  tên người thực hiện
     * @return array{title: string, text: string, url: string}
     */
    public function message(CookingBatch $batch, string $text, ?string $actor): array
    {
        return [
            'title' => 'BếpMinhBạch · '.$batch->name.' ('.Format::date($batch->starts_on).')',
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
                'text' => '*'.$this->slack($m['title'])."*\n".$this->slack($m['text'])."\n<".$m['url'].'|Mở đợt nấu>',
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
