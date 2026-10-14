<?php

namespace App\Enums;

/** Trạng thái tài khoản dùng cho bộ lọc danh sách (ánh xạ sang cột users.is_active). */
enum AccountState: string
{
    case Active = 'active';
    case Locked = 'locked';

    /**
     * Nhãn hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Locked => 'Đã khóa',
        };
    }

    /**
     * Giá trị tương ứng của cột is_active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
