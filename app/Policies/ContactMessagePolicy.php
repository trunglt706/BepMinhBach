<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/** Liên hệ chứa thông tin cá nhân của khách: chỉ điều phối viên trở lên xem, xử lý và xóa. */
class ContactMessagePolicy
{
    /**
     * Xem danh sách liên hệ.
     *
     * @param  User  $user  người đăng nhập
     */
    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Đánh dấu đã xử lý / chưa xử lý.
     *
     * @param  User  $user  người đăng nhập
     * @param  ContactMessage  $message  liên hệ
     */
    public function update(User $user, ContactMessage $message): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Xóa liên hệ (sau khi đã xử lý xong).
     *
     * @param  User  $user  người đăng nhập
     * @param  ContactMessage  $message  liên hệ
     */
    public function delete(User $user, ContactMessage $message): bool
    {
        return $user->isCoordinator();
    }
}
