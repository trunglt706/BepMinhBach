<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Chưa làm',
            self::InProgress => 'Đang làm',
            self::Blocked => 'Đang bị vướng',
            self::Done => 'Hoàn thành',
        };
    }

    public function badgeClass(): string
    {
        return 'status-pill task-'.$this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Todo => 'bi-circle',
            self::InProgress => 'bi-arrow-repeat',
            self::Blocked => 'bi-exclamation-octagon',
            self::Done => 'bi-check-circle-fill',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
