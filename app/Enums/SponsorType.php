<?php

namespace App\Enums;

/** Phân loại đơn vị tài trợ / hỗ trợ (thứ tự khai báo = thứ tự hiển thị). */
enum SponsorType: string
{
    case Sponsor = 'sponsor';
    case Partner = 'partner';
    case InKind = 'in_kind';
    case Media = 'media';

    /**
     * Nhãn tiếng Việt (số ít).
     */
    public function label(): string
    {
        return match ($this) {
            self::Sponsor => 'Nhà tài trợ',
            self::Partner => 'Đơn vị đồng hành',
            self::InKind => 'Hỗ trợ hiện vật',
            self::Media => 'Truyền thông',
        };
    }

    /**
     * Mô tả ngắn cho người nhập liệu.
     */
    public function description(): string
    {
        return match ($this) {
            self::Sponsor => 'Đóng góp tài chính cho chương trình.',
            self::Partner => 'Cùng tổ chức: nhân lực, địa điểm, kết nối.',
            self::InKind => 'Tặng nguyên liệu, vật phẩm, phần quà.',
            self::Media => 'Lan tỏa thông tin về chương trình.',
        };
    }

    /**
     * Tên icon Bootstrap Icons.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Sponsor => 'bi-award',
            self::Partner => 'bi-people',
            self::InKind => 'bi-gift',
            self::Media => 'bi-megaphone',
        };
    }
}
