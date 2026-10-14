<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\Role;
use App\Http\Requests\UserIndexRequest;
use App\Http\Requests\UserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Quản lý tài khoản (quản trị viên). Tài khoản không bị xóa để giữ lịch sử, chỉ khóa/mở khóa.
 * Log chỉ ghi số hiệu tài khoản và vai trò — không ghi tên, email hay mật khẩu.
 */
class UserController extends Controller
{
    /**
     * Danh sách tài khoản, lọc theo tên/email, vai trò, trạng thái.
     *
     * @param  UserIndexRequest  $request  bộ lọc đã chuẩn hóa + quyền viewAny
     */
    public function index(UserIndexRequest $request): View
    {
        $q = $request->keyword();
        $role = $request->role();
        $state = $request->state();

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->when($role, fn ($query) => $query->where('role', $role->value))
            ->when($state, fn ($query) => $query->where('is_active', $state->isActive()))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users', 'q', 'role', 'state'));
    }

    /**
     * Form tạo tài khoản (mặc định vai trò Tình nguyện viên).
     */
    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['user' => new User(['role' => Role::Volunteer])]);
    }

    /**
     * Tạo tài khoản đang hoạt động.
     *
     * @param  UserRequest  $request  dữ liệu đã kiểm tra + quyền create
     */
    public function store(UserRequest $request): JsonResponse|RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([...$request->validated(), 'is_active' => true]);
            ActivityLog::record(null, $request->user(), ActivityAction::UserCreated,
                'Tạo tài khoản #'.$user->id.' với vai trò '.$user->role->label().'.');

            return $user;
        });

        return $this->done($request, redirect()->route('users.index'), 'Đã tạo tài khoản cho '.$user->name.'.');
    }

    /**
     * Form sửa tài khoản.
     *
     * @param  User  $user  tài khoản cần sửa
     */
    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    /**
     * Lưu tài khoản; để trống mật khẩu thì giữ mật khẩu cũ.
     *
     * @param  UserRequest  $request  dữ liệu đã kiểm tra + quyền update
     * @param  User  $user  tài khoản cần sửa
     */
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
            ActivityLog::record(null, $request->user(), ActivityAction::UserUpdated, $summary);
        });

        return $this->done($request, redirect()->route('users.index'), 'Đã lưu tài khoản '.$user->name.'.');
    }

    /**
     * Khóa/mở khóa tài khoản; khóa thì vô hiệu cookie "ghi nhớ đăng nhập".
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  User  $user  tài khoản cần đổi
     */
    public function toggleActive(Request $request, User $user): JsonResponse|RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        DB::transaction(function () use ($user, $request) {
            $user->is_active = ! $user->is_active;
            if (! $user->is_active) {
                $user->setRememberToken(null);
            }
            $user->save();

            ActivityLog::record(null, $request->user(), ActivityAction::UserToggled,
                ($user->is_active ? 'Mở khóa' : 'Khóa').' tài khoản #'.$user->id.'.');
        });

        return $this->done($request, redirect()->route('users.index'),
            ($user->is_active ? 'Đã mở khóa' : 'Đã khóa').' tài khoản '.$user->name.'.');
    }
}
