<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Quyền trên nguyên liệu: sửa/xóa theo checklist của đợt; báo thiếu cho mọi thành viên. */
class IngredientPolicy
{
    /**
     * Sửa nguyên liệu (xem CookingBatchPolicy::manageChecklist).
     *
     * @param  User  $user  người đăng nhập
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function update(User $user, Ingredient $ingredient): Response
    {
        return app(CookingBatchPolicy::class)->manageChecklist($user, $ingredient->batch);
    }

    /**
     * Xóa nguyên liệu: cùng điều kiện với sửa.
     *
     * @param  User  $user  người đăng nhập
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function delete(User $user, Ingredient $ingredient): Response
    {
        return $this->update($user, $ingredient);
    }

    /**
     * Báo thiếu nguyên liệu: mọi tài khoản đang hoạt động khi đợt còn mở.
     *
     * @param  User  $user  người đăng nhập
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function reportShortage(User $user, Ingredient $ingredient): Response
    {
        return app(CookingBatchPolicy::class)->reportIssues($user, $ingredient->batch);
    }

    /**
     * Gỡ cảnh báo thiếu: điều phối viên hoặc chính người đã báo, khi đợt còn mở.
     *
     * @param  User  $user  người đăng nhập
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function resolveShortage(User $user, Ingredient $ingredient): Response
    {
        if (! $user->isCoordinator() && $ingredient->restock_reported_by !== $user->id) {
            return Response::deny('Chỉ điều phối viên hoặc người đã báo thiếu mới gỡ được cảnh báo.');
        }

        return app(CookingBatchPolicy::class)->reportIssues($user, $ingredient->batch);
    }
}
