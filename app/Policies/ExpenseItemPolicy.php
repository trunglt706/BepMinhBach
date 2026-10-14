<?php

namespace App\Policies;

use App\Models\ExpenseItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Quyền trên khoản chi: theo quyền quản lý chi phí của đợt nấu chứa khoản chi. */
class ExpenseItemPolicy
{
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
