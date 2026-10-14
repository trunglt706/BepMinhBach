<?php

namespace App\Policies;

use App\Models\CookingBatch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CookingBatchPolicy
{
    public const LOCKED_MESSAGE = 'Đợt nấu đã hoàn tất hoặc đã hủy. Cần mở lại đợt trước khi chỉnh sửa.';

    /** Mọi tài khoản đang hoạt động đều xem được đợt nấu (để biết việc cần làm). */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, CookingBatch $batch): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isCoordinator();
    }

    public function update(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    public function delete(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->hasHistory()
            ? Response::deny('Đợt nấu đã có dữ liệu nên không thể xóa. Hãy dùng “Hủy đợt” để giữ lại lịch sử.')
            : Response::allow();
    }

    /** Chuyển trạng thái đơn giản: nháp ↔ đã lên kế hoạch → đang diễn ra. */
    public function changeStatus(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    public function complete(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->status->canComplete()
            ? Response::allow()
            : Response::deny('Chỉ hoàn tất được đợt đã lên kế hoạch hoặc đang diễn ra.');
    }

    public function cancel(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->status->canCancel()
            ? Response::allow()
            : Response::deny('Đợt nấu này đã hoàn tất hoặc đã hủy.');
    }

    public function reopen(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->isLocked()
            ? Response::allow()
            : Response::deny('Đợt nấu đang mở, không cần mở lại.');
    }

    /** Thêm nguyên liệu, công việc. */
    public function manageChecklist(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    public function recordResults(User $user, CookingBatch $batch): Response
    {
        if (! $user->isRecorder()) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }

    public function manageExpenses(User $user, CookingBatch $batch): Response
    {
        return $this->recordResults($user, $batch);
    }

    /** Xem số suất thực tế và chi phí. */
    public function viewFinancials(User $user, CookingBatch $batch): bool
    {
        return $user->isRecorder();
    }

    /** Báo thiếu nguyên liệu — mọi tài khoản đang hoạt động, khi đợt còn mở. */
    public function reportIssues(User $user, CookingBatch $batch): Response
    {
        if (! $user->is_active) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }

    private function coordinatorOnUnlocked(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }
}
