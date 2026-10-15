<?php

namespace App\Enums;

/**
 * Trạng thái chương trình: Nháp → Đã lên kế hoạch → Đang diễn ra → Đã hoàn tất; có thể Hủy.
 * Hoàn tất/Hủy là trạng thái khóa (chỉ mở lại được qua thao tác có ghi lý do).
 */
enum BatchStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Nhãn tiếng Việt hiển thị cho người dùng.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Planned => 'Đã lên kế hoạch',
            self::InProgress => 'Đang diễn ra',
            self::Completed => 'Đã hoàn tất',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Lớp CSS của nhãn trạng thái (xem .status-pill trong app.css).
     */
    public function badgeClass(): string
    {
        return 'status-pill status-'.$this->value;
    }

    /**
     * Tên icon Bootstrap Icons đại diện cho trạng thái.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'bi-pencil',
            self::Planned => 'bi-calendar-check',
            self::InProgress => 'bi-fire',
            self::Completed => 'bi-check-circle',
            self::Cancelled => 'bi-x-circle',
        };
    }

    /**
     * Chương trình đã khóa: không nhận cập nhật thông thường cho tới khi được mở lại.
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Các trạng thái chuyển tới được bằng thao tác đơn giản (không cần form riêng).
     * Hoàn tất, hủy và mở lại có màn hình xác nhận riêng.
     *
     * @return list<self>
     */
    public function simpleTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Planned],
            self::Planned => [self::Draft, self::InProgress],
            default => [],
        };
    }

    /**
     * Có thể hoàn tất từ trạng thái này không (đã lên kế hoạch hoặc đang diễn ra).
     */
    public function canComplete(): bool
    {
        return in_array($this, [self::Planned, self::InProgress], true);
    }

    /**
     * Có thể hủy từ trạng thái này không (mọi trạng thái chưa khóa).
     */
    public function canCancel(): bool
    {
        return ! $this->isLocked();
    }

    /**
     * Tất cả giá trị chuỗi (dùng cho validation / migration).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
