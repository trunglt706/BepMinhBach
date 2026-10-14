<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $q = trim((string) $request->query('q', ''));
        $role = Role::tryFrom((string) $request->query('role'));
        $state = in_array($request->query('state'), ['active', 'locked'], true) ? $request->query('state') : null;

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->when($role, fn ($query) => $query->where('role', $role->value))
            ->when($state, fn ($query) => $query->where('is_active', $state === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users', 'q', 'role', 'state'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['user' => new User(['role' => Role::Volunteer])]);
    }

    public function store(UserRequest $request): JsonResponse|RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([...$request->validated(), 'is_active' => true]);
            ActivityLog::record(null, $request->user(), 'user.created',
                'Tạo tài khoản #'.$user->id.' với vai trò '.$user->role->label().'.');

            return $user;
        });

        return $this->done($request, redirect()->route('users.index'), 'Đã tạo tài khoản cho '.$user->name.'.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $request) {
            $oldRole = $user->role;
            $user->update($data);

            $summary = 'Sửa tài khoản #'.$user->id.'.';
            if ($oldRole !== $user->role) {
                $summary .= ' Vai trò: '.$oldRole->label().' → '.$user->role->label().'.';
            }
            if (isset($data['password'])) {
                $summary .= ' Đặt lại mật khẩu.';
            }
            ActivityLog::record(null, $request->user(), 'user.updated', $summary);
        });

        return $this->done($request, redirect()->route('users.index'), 'Đã lưu tài khoản '.$user->name.'.');
    }

    public function toggleActive(Request $request, User $user): JsonResponse|RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        DB::transaction(function () use ($user, $request) {
            $user->is_active = ! $user->is_active;
            // Vô hiệu cookie "ghi nhớ đăng nhập" cũ khi khóa.
            if (! $user->is_active) {
                $user->setRememberToken(null);
            }
            $user->save();

            ActivityLog::record(null, $request->user(), 'user.toggled',
                ($user->is_active ? 'Mở khóa' : 'Khóa').' tài khoản #'.$user->id.'.');
        });

        return $this->done($request, redirect()->route('users.index'),
            ($user->is_active ? 'Đã mở khóa' : 'Đã khóa').' tài khoản '.$user->name.'.');
    }
}
