<?php

namespace App\Enums;

/**
 * Trạng thái bài viết: bản nháp → chờ duyệt (tác giả gửi) → đã đăng (quản trị viên duyệt).
 * Chỉ "Đã đăng" hiện ở trang công khai.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';

    /**
     * Nhãn tiếng Việt hiển thị cho người dùng.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::Pending => 'Chờ duyệt',
            self::Published => 'Đã đăng',
        };
    }

    /**
     * Lớp CSS của nhãn: dùng lại màu nhãn trạng thái chương trình
     * (nháp = xám, chờ duyệt = xanh dương như "đã lên kế hoạch", đã đăng = xanh lá như "đã hoàn tất").
     */
    public function badgeClass(): string
    {
        $look = match ($this) {
            self::Draft => BatchStatus::Draft,
            self::Pending => BatchStatus::Planned,
            self::Published => BatchStatus::Completed,
        };

        return $look->badgeClass();
    }

    /**
     * Tên icon Bootstrap Icons đại diện cho trạng thái.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'bi-pencil',
            self::Pending => 'bi-hourglass-split',
            self::Published => 'bi-globe2',
        };
    }
}
