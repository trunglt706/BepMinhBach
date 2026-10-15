<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email chứa link đặt lại mật khẩu (tiếng Việt).
 *
 * Gửi nền qua queue: trang "Quên mật khẩu" trả lời ngay, và thời gian phản hồi không để lộ email có tài khoản hay
 * không (gửi mail chậm hơn nhiều so với "không tìm thấy"). Link hết hạn theo config auth.passwords.users.expire.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $token  token đặt lại mật khẩu (bản gốc; trong DB chỉ lưu bản băm)
     */
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Bản ghi trong tab "Thông báo đã nhận" — KHÔNG lưu token/link (ai đọc được DB cũng không đặt lại được mật khẩu).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => 'bi-envelope',
            'title' => 'Email đặt lại mật khẩu',
            'body' => 'Đã gửi link đặt lại mật khẩu tới email của tài khoản.',
            'url' => null,
        ];
    }

    /**
     * Nội dung email.
     *
     * @param  object  $notifiable  người dùng (có email)
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu Góp Gió')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line('Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản Góp Gió của bạn.')
            ->action('Đặt mật khẩu mới', $url)
            ->line("Link chỉ dùng được một lần và hết hạn sau {$minutes} phút.")
            ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này — mật khẩu hiện tại vẫn giữ nguyên.')
            ->salutation('Đội Góp Gió');
    }
}
