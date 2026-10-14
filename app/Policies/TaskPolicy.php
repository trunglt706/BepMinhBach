<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    public function update(User $user, Task $task): Response
    {
        return app(CookingBatchPolicy::class)->manageChecklist($user, $task->batch);
    }

    public function delete(User $user, Task $task): Response
    {
        return $this->update($user, $task);
    }

    /** Người được giao hoặc điều phối viên được cập nhật tiến độ. */
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
