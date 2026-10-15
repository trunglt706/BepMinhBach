<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Quyền trên công việc: sửa/xóa theo checklist của chương trình; cập nhật tiến độ cho người được giao. */
class TaskPolicy
{
    /**
     * Phạm vi dữ liệu: phải được xem chương trình chứa công việc (CookingBatch::isVisibleTo), không thì 404.
     *
     * @param  User  $user  người đăng nhập
     * @param  string  $ability  tên quyền
     * @param  mixed  ...$arguments  đối số (công việc)
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?Response
    {
        $task = $arguments[0] ?? null;

        return $task instanceof Task && ! $task->batch->isVisibleTo($user) ? Response::denyAsNotFound() : null;
    }

    /**
     * Sửa / phân công lại công việc (xem CookingBatchPolicy::manageChecklist).
     *
     * @param  User  $user  người đăng nhập
     * @param  Task  $task  công việc
     */
    public function update(User $user, Task $task): Response
    {
        return app(CookingBatchPolicy::class)->manageChecklist($user, $task->batch);
    }

    /**
     * Xóa công việc: cùng điều kiện với sửa.
     *
     * @param  User  $user  người đăng nhập
     * @param  Task  $task  công việc
     */
    public function delete(User $user, Task $task): Response
    {
        return $this->update($user, $task);
    }

    /**
     * Cập nhật tiến độ: người được giao hoặc điều phối viên, khi chương trình còn mở.
     *
     * @param  User  $user  người đăng nhập
     * @param  Task  $task  công việc
     */
    public function updateStatus(User $user, Task $task): Response
    {
        if (! $user->is_active || (! $user->isCoordinator() && $task->assignee_id !== $user->id)) {
            return Response::deny('Bạn chỉ cập nhật được công việc được giao cho mình.');
        }

        return $task->batch->isLocked()
            ? Response::deny(CookingBatchPolicy::LOCKED_MESSAGE)
            : Response::allow();
    }
}
