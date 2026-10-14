<?php

namespace App\Enums;

enum BatchStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

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

    public function badgeClass(): string
    {
        return 'status-pill status-'.$this->value;
    }

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

    /** Đợt đã khóa: không nhận cập nhật thông thường cho tới khi được mở lại. */
    public function isLocked(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Chuyển trạng thái đơn giản (không cần form riêng).
     * Hoàn tất, hủy và mở lại có màn hình xác nhận riêng.
     */
    public function simpleTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Planned],
            self::Planned => [self::Draft, self::InProgress],
            default => [],
        };
    }

    public function canComplete(): bool
    {
        return in_array($this, [self::Planned, self::InProgress], true);
    }

    public function canCancel(): bool
    {
        return ! $this->isLocked();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
