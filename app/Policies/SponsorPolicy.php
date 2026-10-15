<?php

namespace App\Policies;

use App\Models\Sponsor;
use App\Models\User;

/**
 * Quyền trên danh bạ đơn vị tài trợ / hỗ trợ: điều phối viên trở lên xem, thêm, sửa (để gắn vào chương trình
 * mình phụ trách); chỉ quản trị viên xóa (xóa đơn vị là gỡ khỏi mọi chương trình đã tham gia).
 */
class SponsorPolicy
{
    /**
     * @param  User  $user  người đăng nhập
     */
    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }

    /**
     * @param  User  $user  người đăng nhập
     */
    public function create(User $user): bool
    {
        return $user->isCoordinator();
    }

    /**
     * @param  User  $user  người đăng nhập
     * @param  Sponsor  $sponsor  đơn vị
     */
    public function update(User $user, Sponsor $sponsor): bool
    {
        return $user->isCoordinator();
    }

    /**
     * @param  User  $user  người đăng nhập
     * @param  Sponsor  $sponsor  đơn vị
     */
    public function delete(User $user, Sponsor $sponsor): bool
    {
        return $user->isAdmin();
    }
}
