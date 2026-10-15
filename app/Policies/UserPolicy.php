<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Quản lý tài khoản: chỉ quản trị viên. Không có quyền xóa tài khoản (giữ lịch sử). */
class UserPolicy
{
    /**
     * Xem danh sách tài khoản.
     *
     * @param  User  $user  người đăng nhập
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Xem chi tiết tài khoản (thông tin, nhật ký hoạt động, thông báo, thiết bị đăng nhập).
     *
     * @param  User  $user  người đăng nhập
     * @param  User  $target  tài khoản cần xem
     */
    public function view(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Đăng xuất tài khoản khỏi thiết bị (xóa phiên, vô hiệu "ghi nhớ đăng nhập").
     *
     * @param  User  $user  người đăng nhập
     * @param  User  $target  tài khoản cần đăng xuất
     */
    public function manageSessions(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Tạo tài khoản.
     *
     * @param  User  $user  người đăng nhập
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Sửa tài khoản (tên, email, vai trò, đặt lại mật khẩu).
     *
     * @param  User  $user  người đăng nhập
     * @param  User  $target  tài khoản cần sửa
     */
    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Khóa/mở khóa tài khoản; không tự khóa chính mình để tránh mất quyền quản trị.
     *
     * @param  User  $user  người đăng nhập
     * @param  User  $target  tài khoản cần đổi
     */
    public function toggleActive(User $user, User $target): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $user->is($target)
            ? Response::deny('Bạn không thể tự khóa tài khoản của chính mình.')
            : Response::allow();
    }
}
