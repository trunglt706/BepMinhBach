<?php

namespace App\Enums;

/**
 * Kênh nhận thông báo của đợt nấu. Mỗi kênh có bộ thông số riêng (fields) và mẫu link tham gia hợp lệ.
 * Chỉ gửi tới địa chỉ cố định của từng dịch vụ (không cho nhập URL tùy ý → không bị lợi dụng gọi vào mạng nội bộ).
 */
enum NotificationDriver: string
{
    case Slack = 'slack';
    case Telegram = 'telegram';
    case Chatwork = 'chatwork';

    /**
     * Tên hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Slack => 'Slack',
            self::Telegram => 'Telegram',
            self::Chatwork => 'Chatwork',
        };
    }

    /**
     * Icon Bootstrap Icons.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Slack => 'bi-slack',
            self::Telegram => 'bi-telegram',
            self::Chatwork => 'bi-chat-square-dots',
        };
    }

    /**
     * Thông số cấu hình: khóa => [nhãn, gợi ý, bí mật?, quy tắc kiểm tra].
     * Thông số "bí mật" được mã hóa khi lưu, không bao giờ hiện lại đầy đủ; để trống khi sửa = giữ giá trị cũ.
     *
     * @return array<string, array{label: string, help: string, secret: bool, rules: list<string>, placeholder: string}>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Slack => [
                'webhook_url' => [
                    'label' => 'Incoming Webhook URL',
                    'help' => 'Slack → Apps → Incoming Webhooks → Add to channel → chép "Webhook URL".',
                    'secret' => true,
                    'rules' => ['regex:#^https://hooks\.slack\.com/services/[A-Za-z0-9/_-]{20,200}$#'],
                    'placeholder' => 'https://hooks.slack.com/services/T000/B000/xxxx',
                ],
            ],
            self::Telegram => [
                'bot_token' => [
                    'label' => 'Bot token',
                    'help' => 'Tạo bot với @BotFather (lệnh /newbot), chép token. Thêm bot vào nhóm/kênh và cho quyền gửi tin.',
                    'secret' => true,
                    'rules' => ['regex:/^\d{5,12}:[A-Za-z0-9_-]{30,64}$/'],
                    'placeholder' => '123456789:AAH…',
                ],
                'chat_id' => [
                    'label' => 'Chat ID',
                    'help' => 'ID nhóm (số âm, ví dụ -1001234567890) hoặc tên kênh công khai (@ten_kenh).',
                    'secret' => false,
                    'rules' => ['regex:/^(-?\d{5,20}|@[A-Za-z][A-Za-z0-9_]{4,31})$/'],
                    'placeholder' => '-1001234567890',
                ],
            ],
            self::Chatwork => [
                'api_token' => [
                    'label' => 'API token',
                    'help' => 'Chatwork → Cài đặt dịch vụ → API Token. Nên dùng tài khoản riêng cho bot.',
                    'secret' => true,
                    'rules' => ['regex:/^[A-Za-z0-9]{20,64}$/'],
                    'placeholder' => '0123456789abcdef…',
                ],
                'room_id' => [
                    'label' => 'Room ID',
                    'help' => 'Số cuối trong địa chỉ phòng chat: https://www.chatwork.com/#!rid123456789 → 123456789.',
                    'secret' => false,
                    'rules' => ['regex:/^\d{1,20}$/'],
                    'placeholder' => '123456789',
                ],
            ],
        };
    }

    /**
     * Mẫu link tham gia hợp lệ (để người dùng bấm vào nhận thông báo). Chỉ https và đúng tên miền của dịch vụ.
     */
    public function joinUrlPattern(): string
    {
        return match ($this) {
            self::Slack => '#^https://(join\.slack\.com|[a-z0-9-]+\.slack\.com|app\.slack\.com)/\S+$#i',
            self::Telegram => '#^https://t\.me/\S+$#i',
            self::Chatwork => '#^https://(www\.)?chatwork\.com/\S+$#i',
        };
    }

    /**
     * Ví dụ link tham gia (hiện trong form).
     */
    public function joinUrlExample(): string
    {
        return match ($this) {
            self::Slack => 'https://join.slack.com/t/bep-minh-bach/shared_invite/…',
            self::Telegram => 'https://t.me/+AbCdEf… hoặc https://t.me/ten_kenh',
            self::Chatwork => 'https://www.chatwork.com/g/abc123',
        };
    }
}
