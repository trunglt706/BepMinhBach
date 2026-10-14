<?php

namespace App\Policies;

use App\Models\CookingBatch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Quyền trên đợt nấu. Vai trò theo cấp (xem App\Enums\Role); đợt đã khóa (hoàn tất/hủy)
 * không nhận chỉnh sửa cho tới khi được mở lại.
 */
class CookingBatchPolicy
{
    /** Thông báo khi thao tác trên đợt đã khóa. */
    public const LOCKED_MESSAGE = 'Đợt nấu đã hoàn tất hoặc đã hủy. Cần mở lại đợt trước khi chỉnh sửa.';

    /**
     * Xem danh sách đợt: mọi tài khoản đang hoạt động (để biết việc cần làm).
     *
     * @param  User  $user  người đăng nhập
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Xem chi tiết đợt: mọi tài khoản đang hoạt động.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function view(User $user, CookingBatch $batch): bool
    {
        return $user->is_active;
    }

    /**
     * Tạo đợt: điều phối viên trở lên.
     *
     * @param  User  $user  người đăng nhập
     */
    public function create(User $user): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Sửa thông tin đợt: điều phối viên, đợt chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function update(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    /**
     * Xóa đợt: chỉ đợt nháp chưa có dữ liệu (còn lại phải "Hủy" để giữ lịch sử).
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function delete(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->hasHistory()
            ? Response::deny('Đợt nấu đã có dữ liệu nên không thể xóa. Hãy dùng “Hủy đợt” để giữ lại lịch sử.')
            : Response::allow();
    }

    /**
     * Chuyển trạng thái đơn giản: nháp ↔ đã lên kế hoạch → đang diễn ra.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function changeStatus(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    /**
     * Hoàn tất (khóa) đợt: điều phối viên, đợt đã lên kế hoạch hoặc đang diễn ra.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function complete(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->status->canComplete()
            ? Response::allow()
            : Response::deny('Chỉ hoàn tất được đợt đã lên kế hoạch hoặc đang diễn ra.');
    }

    /**
     * Hủy đợt: điều phối viên, đợt chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function cancel(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->status->canCancel()
            ? Response::allow()
            : Response::deny('Đợt nấu này đã hoàn tất hoặc đã hủy.');
    }

    /**
     * Mở lại đợt đã khóa: điều phối viên.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function reopen(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->isLocked()
            ? Response::allow()
            : Response::deny('Đợt nấu đang mở, không cần mở lại.');
    }

    /**
     * Thêm/sửa nguyên liệu, công việc: điều phối viên, đợt chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function manageChecklist(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    /**
     * Nhập số suất thực tế: người ghi nhận trở lên, đợt chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function recordResults(User $user, CookingBatch $batch): Response
    {
        if (! $user->isRecorder()) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }

    /**
     * Thêm/sửa/xóa khoản chi: cùng điều kiện với nhập số suất.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function manageExpenses(User $user, CookingBatch $batch): Response
    {
        return $this->recordResults($user, $batch);
    }

    /**
     * Xem số suất thực tế, chi phí và lịch sử thay đổi: người ghi nhận trở lên.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function viewFinancials(User $user, CookingBatch $batch): bool
    {
        return $user->isRecorder();
    }

    /**
     * Báo thiếu nguyên liệu: mọi tài khoản đang hoạt động, khi đợt còn mở.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function reportIssues(User $user, CookingBatch $batch): Response
    {
        if (! $user->is_active) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }

    /**
     * Điều kiện chung: điều phối viên trở lên và đợt chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  đợt nấu
     */
    private function coordinatorOnUnlocked(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }
}
