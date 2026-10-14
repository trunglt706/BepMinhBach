<?php

namespace App\Enums;

/** Trạng thái công việc trong checklist của đợt nấu. */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';

    /**
     * Nhãn tiếng Việt hiển thị cho người dùng.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Chưa làm',
            self::InProgress => 'Đang làm',
            self::Blocked => 'Đang bị vướng',
            self::Done => 'Hoàn thành',
        };
    }

    /**
     * Lớp CSS của nhãn trạng thái (xem .task-* trong app.css).
     */
    public function badgeClass(): string
    {
        return 'status-pill task-'.$this->value;
    }

    /**
     * Tên icon Bootstrap Icons đại diện cho trạng thái.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Todo => 'bi-circle',
            self::InProgress => 'bi-arrow-repeat',
            self::Blocked => 'bi-exclamation-octagon',
            self::Done => 'bi-check-circle-fill',
        };
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
