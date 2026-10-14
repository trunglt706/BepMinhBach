<?php

namespace App\Enums;

/** Trạng thái bài viết: bản nháp (chỉ người quản lý thấy) hoặc đã đăng (công khai). */
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    /**
     * Nhãn tiếng Việt hiển thị cho người dùng.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::Published => 'Đã đăng',
        };
    }

    /**
     * Lớp CSS của nhãn: dùng lại màu nhãn trạng thái đợt nấu (nháp = xám, đã đăng = xanh như "đã hoàn tất").
     */
    public function badgeClass(): string
    {
        $look = $this === self::Draft ? BatchStatus::Draft : BatchStatus::Completed;

        return $look->badgeClass();
    }

    /**
     * Tên icon Bootstrap Icons đại diện cho trạng thái.
     */
    public function icon(): string
    {
        return $this === self::Draft ? 'bi-pencil' : 'bi-globe2';
    }
}
