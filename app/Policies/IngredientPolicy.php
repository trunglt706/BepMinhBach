<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IngredientPolicy
{
    public function update(User $user, Ingredient $ingredient): Response
    {
        return app(CookingBatchPolicy::class)->manageChecklist($user, $ingredient->batch);
    }

    public function delete(User $user, Ingredient $ingredient): Response
    {
        return $this->update($user, $ingredient);
    }

    public function reportShortage(User $user, Ingredient $ingredient): Response
    {
        return app(CookingBatchPolicy::class)->reportIssues($user, $ingredient->batch);
    }

    /** Gỡ cảnh báo thiếu: điều phối viên hoặc chính người đã báo. */
    public function resolveShortage(User $user, Ingredient $ingredient): Response
    {
        if (! $user->isCoordinator() && $ingredient->restock_reported_by !== $user->id) {
            return Response::deny('Chỉ điều phối viên hoặc người đã báo thiếu mới gỡ được cảnh báo.');
        }

        return app(CookingBatchPolicy::class)->reportIssues($user, $ingredient->batch);
    }
}
