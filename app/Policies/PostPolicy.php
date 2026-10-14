<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/** Điều phối viên trở lên viết và quản lý bài viết công khai. Khách chỉ xem bài đã đăng (không qua policy). */
class PostPolicy
{
    /**
     * Xem danh sách bài viết nội bộ (gồm bản nháp).
     *
     * @param  User  $user  người đăng nhập
     */
    public function viewAny(User $user): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Viết bài mới.
     *
     * @param  User  $user  người đăng nhập
     */
    public function create(User $user): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Sửa bài (và xem trước bản nháp trên trang công khai).
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function update(User $user, Post $post): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Đăng hoặc gỡ bài.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function publish(User $user, Post $post): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Xóa hẳn bài viết.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function delete(User $user, Post $post): bool
    {
        return $user->isCoordinator();
    }
}
