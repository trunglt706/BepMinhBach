<?php

namespace App\Policies;

use App\Models\CookingBatch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Quyền trên chương trình. Vai trò theo cấp (xem App\Enums\Role); chương trình đã khóa (hoàn tất/hủy)
 * không nhận chỉnh sửa cho tới khi được mở lại.
 */
class CookingBatchPolicy
{
    /** Thông báo khi thao tác trên chương trình đã khóa. */
    public const LOCKED_MESSAGE = 'Chương trình đã hoàn tất hoặc đã hủy. Cần mở lại chương trình trước khi chỉnh sửa.';

    /**
     * Phạm vi dữ liệu: mọi quyền trên MỘT chương trình cụ thể đều yêu cầu được xem chương trình đó (CookingBatch::isVisibleTo —
     * điều phối viên: chương trình mình điều phối; ghi nhận/tình nguyện viên: chương trình có việc giao cho mình). Không được xem
     * thì trả 404 (không tiết lộ chương trình có tồn tại). null = tiếp tục kiểm tra quyền như bình thường.
     *
     * @param  User  $user  người đăng nhập
     * @param  string  $ability  tên quyền
     * @param  mixed  ...$arguments  đối số (chương trình nếu có)
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?Response
    {
        $batch = $arguments[0] ?? null;

        return $batch instanceof CookingBatch && ! $batch->isVisibleTo($user) ? Response::denyAsNotFound() : null;
    }

    /**
     * Xem danh sách chương trình: mọi tài khoản đang hoạt động (danh sách tự lọc theo CookingBatch::scopeVisibleTo).
     *
     * @param  User  $user  người đăng nhập
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Xem chi tiết chương trình: tài khoản đang hoạt động trong phạm vi được xem (kiểm tra ở before()).
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function view(User $user, CookingBatch $batch): bool
    {
        return $user->is_active;
    }

    /**
     * Tạo chương trình: điều phối viên trở lên.
     *
     * @param  User  $user  người đăng nhập
     */
    public function create(User $user): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Sửa thông tin chương trình: điều phối viên, chương trình chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function update(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    /**
     * Xóa chương trình: chỉ chương trình nháp chưa có dữ liệu (còn lại phải "Hủy" để giữ lịch sử).
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function delete(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->hasHistory()
            ? Response::deny('Chương trình đã có dữ liệu nên không thể xóa. Hãy dùng “Hủy chương trình” để giữ lại lịch sử.')
            : Response::allow();
    }

    /**
     * Chuyển trạng thái đơn giản: nháp ↔ đã lên kế hoạch → đang diễn ra.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function changeStatus(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    /**
     * Hoàn tất (khóa) chương trình: điều phối viên, chương trình đã lên kế hoạch hoặc đang diễn ra.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function complete(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->status->canComplete()
            ? Response::allow()
            : Response::deny('Chỉ hoàn tất được chương trình đã lên kế hoạch hoặc đang diễn ra.');
    }

    /**
     * Hủy chương trình: điều phối viên, chương trình chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function cancel(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->status->canCancel()
            ? Response::allow()
            : Response::deny('Chương trình này đã hoàn tất hoặc đã hủy.');
    }

    /**
     * Mở lại chương trình đã khóa: điều phối viên.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function reopen(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->isLocked()
            ? Response::allow()
            : Response::deny('Chương trình đang mở, không cần mở lại.');
    }

    /**
     * Thêm/sửa nguyên liệu, công việc: điều phối viên, chương trình chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function manageChecklist(User $user, CookingBatch $batch): Response
    {
        return $this->coordinatorOnUnlocked($user, $batch);
    }

    /**
     * Nhập số suất thực tế: người ghi nhận trở lên, chương trình chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
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
     * @param  CookingBatch  $batch  chương trình
     */
    public function manageExpenses(User $user, CookingBatch $batch): Response
    {
        return $this->recordResults($user, $batch);
    }

    /**
     * Xem số suất thực tế, chi phí và lịch sử thay đổi: người ghi nhận trở lên.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function viewFinancials(User $user, CookingBatch $batch): bool
    {
        return $user->isRecorder();
    }

    /**
     * Báo thiếu nguyên liệu: mọi tài khoản đang hoạt động, khi chương trình còn mở.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function reportIssues(User $user, CookingBatch $batch): Response
    {
        if (! $user->is_active) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }

    /**
     * Cấu hình kênh thông báo (Slack/Telegram/Chatwork): điều phối viên trở lên, kể cả chương trình đã khóa
     * (cấu hình kênh không đổi dữ liệu chương trình; chương trình có thể được mở lại và vẫn cần thông báo).
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function manageNotifications(User $user, CookingBatch $batch): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Gắn / gỡ đơn vị tài trợ, hỗ trợ: điều phối viên trở lên (trong phạm vi xem — before()), kể cả chương trình
     * đã khóa (thường bổ sung đơn vị để cảm ơn sau khi chương trình kết thúc).
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    public function manageSponsors(User $user, CookingBatch $batch): bool
    {
        return $user->isCoordinator();
    }

    /**
     * Điều kiện chung: điều phối viên trở lên và chương trình chưa khóa.
     *
     * @param  User  $user  người đăng nhập
     * @param  CookingBatch  $batch  chương trình
     */
    private function coordinatorOnUnlocked(User $user, CookingBatch $batch): Response
    {
        if (! $user->isCoordinator()) {
            return Response::deny();
        }

        return $batch->isLocked() ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }
}
