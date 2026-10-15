<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Quyền trên bài viết (quy trình duyệt):
 * - Điều phối viên: tạo bài; xem/sửa/xóa bài CỦA MÌNH; gửi duyệt, rút yêu cầu, tự gỡ bài của mình.
 *   Sửa bài đang đăng → bài về "Chờ duyệt" (ẩn khỏi trang công khai tới khi được duyệt lại).
 * - Quản trị viên: mọi bài; duyệt (đăng), từ chối, gỡ; bài tự viết được đăng ngay.
 * Bài của người khác: 404 (không tiết lộ tồn tại).
 */
class PostPolicy
{
    /**
     * Xem danh sách bài (điều phối viên chỉ thấy bài của mình — lọc ở controller).
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
     * Sửa bài (cũng dùng cho xem trước bài chưa đăng).
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function update(User $user, Post $post): Response
    {
        return $this->ownerOrAdmin($user, $post);
    }

    /**
     * Xóa bài.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function delete(User $user, Post $post): Response
    {
        return $this->ownerOrAdmin($user, $post);
    }

    /**
     * Gửi yêu cầu phát hành: tác giả (không phải quản trị viên — họ đăng thẳng), bài đang là nháp.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function submit(User $user, Post $post): Response
    {
        $owner = $this->ownerOrAdmin($user, $post);
        if ($owner->denied() || $user->isAdmin()) {
            return $owner->denied() ? $owner : Response::deny();
        }

        return $post->status === PostStatus::Draft ? Response::allow() : Response::deny('Chỉ gửi duyệt được bản nháp.');
    }

    /**
     * Rút yêu cầu phát hành (bài về nháp): tác giả, bài đang chờ duyệt.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function withdraw(User $user, Post $post): Response
    {
        $owner = $this->ownerOrAdmin($user, $post);
        if ($owner->denied() || ! $post->isAuthoredBy($user)) {
            return $owner->denied() ? $owner : Response::deny();
        }

        return $post->status === PostStatus::Pending ? Response::allow() : Response::deny('Bài không ở trạng thái chờ duyệt.');
    }

    /**
     * Duyệt và đăng bài: chỉ quản trị viên (bài nháp hoặc chờ duyệt).
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function publish(User $user, Post $post): Response
    {
        if (! $user->isAdmin()) {
            $owner = $this->ownerOrAdmin($user, $post);

            return $owner->denied() ? $owner : Response::deny('Chỉ quản trị viên được duyệt đăng bài.');
        }

        return $post->status === PostStatus::Published ? Response::deny('Bài đã được đăng.') : Response::allow();
    }

    /**
     * Từ chối yêu cầu phát hành: chỉ quản trị viên, bài đang chờ duyệt.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function reject(User $user, Post $post): Response
    {
        if (! $user->isAdmin()) {
            $owner = $this->ownerOrAdmin($user, $post);

            return $owner->denied() ? $owner : Response::deny();
        }

        return $post->status === PostStatus::Pending ? Response::allow() : Response::deny('Bài không ở trạng thái chờ duyệt.');
    }

    /**
     * Gỡ bài khỏi trang công khai (về nháp): tác giả hoặc quản trị viên, bài đang đăng.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    public function unpublish(User $user, Post $post): Response
    {
        $owner = $this->ownerOrAdmin($user, $post);
        if ($owner->denied()) {
            return $owner;
        }

        return $post->status === PostStatus::Published ? Response::allow() : Response::deny('Bài chưa được đăng.');
    }

    /**
     * Tác giả hoặc quản trị viên; bài của người khác trả 404.
     *
     * @param  User  $user  người đăng nhập
     * @param  Post  $post  bài viết
     */
    private function ownerOrAdmin(User $user, Post $post): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $user->isAdmin() || $post->isAuthoredBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
