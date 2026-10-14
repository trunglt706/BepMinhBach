<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /** Không tự khóa chính mình để tránh mất quyền quản trị. */
    public function toggleActive(User $user, User $target): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $user->is($target)
            ? Response::deny('Bạn không thể tự khóa tài khoản của chính mình.')
            : Response::allow();
    }
}
