<?php

namespace App\Policies;

use App\Models\ExpenseItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExpenseItemPolicy
{
    public function update(User $user, ExpenseItem $expense): Response
    {
        return app(CookingBatchPolicy::class)->manageExpenses($user, $expense->batch);
    }

    public function delete(User $user, ExpenseItem $expense): Response
    {
        return $this->update($user, $expense);
    }
}
