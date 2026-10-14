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

    /**
     * Nhãn tiếng Việt hiển thị cho người dùng.
     */
    public function label(): string
    {
        return match ($this) {
            self::Volunteer => 'Tình nguyện viên',
            self::Recorder => 'Người ghi nhận kết quả',
            self::Coordinator => 'Điều phối viên',
            self::Admin => 'Quản trị hệ thống',
        };
    }

    /**
     * Mô tả ngắn quyền hạn (hiện khi chọn vai trò trong form tài khoản).
     */
    public function description(): string
    {
        return match ($this) {
            self::Volunteer => 'Xem đợt nấu, cập nhật việc được giao, báo thiếu nguyên liệu.',
            self::Recorder => 'Như tình nguyện viên, thêm: nhập số suất, chi phí và xem báo cáo.',
            self::Coordinator => 'Như người ghi nhận, thêm: tạo/quản lý đợt nấu, nguyên liệu, công việc, bài viết.',
            self::Admin => 'Toàn quyền, gồm quản lý tài khoản.',
        };
    }

    /**
     * Cấp của vai trò (càng lớn càng nhiều quyền).
     */
    public function level(): int
    {
        return match ($this) {
            self::Volunteer => 1,
            self::Recorder => 2,
            self::Coordinator => 3,
            self::Admin => 4,
        };
    }

    /**
     * Vai trò này có cấp bằng hoặc cao hơn vai trò cho trước không.
     *
     * @param  self  $role  vai trò tối thiểu cần có
     */
    public function atLeast(self $role): bool
    {
        return $this->level() >= $role->level();
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

    /**
     * Giá trị chuỗi của các vai trò từ một cấp trở lên (dùng cho truy vấn whereIn).
     *
     * @param  self  $minimum  vai trò thấp nhất được tính
     * @return list<string>
     */
    public static function valuesAtLeast(self $minimum): array
    {
        return array_values(array_map(
            fn (self $role) => $role->value,
            array_filter(self::cases(), fn (self $role) => $role->atLeast($minimum)),
        ));
    }
}
