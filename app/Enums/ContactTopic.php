<?php

namespace App\Enums;

/**
 * Chủ đề gợi ý của form liên hệ công khai.
 * Cố ý không có chủ đề "đăng ký nhận suất ăn": không thu thập thông tin người nhận qua trang công khai.
 */
enum ContactTopic: string
{
    case Volunteer = 'volunteer';
    case Donation = 'donation';
    case Partnership = 'partnership';
    case Feedback = 'feedback';
    case Other = 'other';

    /**
     * Nhãn tiếng Việt hiển thị cho người dùng.
     */
    public function label(): string
    {
        return match ($this) {
            self::Volunteer => 'Đăng ký làm tình nguyện viên',
            self::Donation => 'Ủng hộ nguyên liệu / kinh phí',
            self::Partnership => 'Hợp tác, truyền thông',
            self::Feedback => 'Góp ý về hoạt động, bài viết',
            self::Other => 'Khác',
        };
    }

    /**
     * Gợi ý nội dung cho ô tin nhắn theo chủ đề.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Volunteer => 'Bạn có thể tham gia vào ngày/giờ nào, việc bạn muốn làm (sơ chế, nấu, chia suất, dọn dẹp…).',
            self::Donation => 'Bạn muốn ủng hộ gì, số lượng khoảng bao nhiêu, thời gian có thể gửi.',
            self::Partnership => 'Đơn vị của bạn và nội dung muốn hợp tác.',
            self::Feedback => 'Góp ý của bạn để đội bếp làm tốt hơn.',
            self::Other => 'Nội dung bạn muốn trao đổi với đội bếp.',
        };
    }

    /**
     * Tên icon Bootstrap Icons.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Volunteer => 'bi-people',
            self::Donation => 'bi-basket',
            self::Partnership => 'bi-megaphone',
            self::Feedback => 'bi-chat-dots',
            self::Other => 'bi-envelope',
        };
    }
}
