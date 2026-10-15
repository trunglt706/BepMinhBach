<?php

namespace App\Policies;

use App\Models\ExpenseItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Quyền trên khoản chi: theo quyền quản lý chi phí của chương trình chứa khoản chi. */
class ExpenseItemPolicy
{
    /**
     * Phạm vi dữ liệu: phải được xem chương trình chứa khoản chi (CookingBatch::isVisibleTo), không thì 404.
     *
     * @param  User  $user  người đăng nhập
     * @param  string  $ability  tên quyền
     * @param  mixed  ...$arguments  đối số (khoản chi)
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?Response
    {
        $expense = $arguments[0] ?? null;

        return $expense instanceof ExpenseItem && ! $expense->batch->isVisibleTo($user) ? Response::denyAsNotFound() : null;
    }

    /**
     * Sửa khoản chi (xem CookingBatchPolicy::manageExpenses).
     *
     * @param  User  $user  người đăng nhập
     * @param  ExpenseItem  $expense  khoản chi
     */
    public function update(User $user, ExpenseItem $expense): Response
    {
        return app(CookingBatchPolicy::class)->manageExpenses($user, $expense->batch);
    }

    /**
     * Xóa khoản chi: cùng điều kiện với sửa.
     *
     * @param  User  $user  người đăng nhập
     * @param  ExpenseItem  $expense  khoản chi
     */
    public function delete(User $user, ExpenseItem $expense): Response
    {
        return $this->update($user, $expense);
    }
}
