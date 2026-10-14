<?php

namespace App\Enums;

/**
 * Vai trò theo cấp: mỗi vai trò cao hơn có đủ quyền của vai trò thấp hơn
 * (admin ⊇ điều phối viên ⊇ người ghi nhận ⊇ tình nguyện viên).
 */
enum Role: string
{
    case Volunteer = 'volunteer';
    case Recorder = 'recorder';
    case Coordinator = 'coordinator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Volunteer => 'Tình nguyện viên',
            self::Recorder => 'Người ghi nhận kết quả',
            self::Coordinator => 'Điều phối viên',
            self::Admin => 'Quản trị hệ thống',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Volunteer => 'Xem đợt nấu, cập nhật việc được giao, báo thiếu nguyên liệu.',
            self::Recorder => 'Như tình nguyện viên, thêm: nhập số suất, chi phí và xem báo cáo.',
            self::Coordinator => 'Như người ghi nhận, thêm: tạo/quản lý đợt nấu, nguyên liệu, công việc.',
            self::Admin => 'Toàn quyền, gồm quản lý tài khoản.',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Volunteer => 1,
            self::Recorder => 2,
            self::Coordinator => 3,
            self::Admin => 4,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
