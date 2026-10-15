<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\User;
use App\Support\UserAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Chi tiết tài khoản cho quản trị viên: 4 tab (thông tin, nhật ký hoạt động, thông báo đã nhận, thiết bị đăng nhập)
 * và đăng xuất tài khoản khỏi thiết bị.
 *
 * Thiết bị lấy từ bảng sessions (SESSION_DRIVER=database). Mã phiên thật KHÔNG bao giờ đưa ra HTML
 * (lộ mã phiên = bị chiếm phiên): mỗi thiết bị được nhận diện bằng mã băm SHA-256 của mã phiên.
 */
class UserDetailController extends Controller
{
    /** Các tab, theo thứ tự hiển thị. */
    public const TABS = [
        'info' => ['label' => 'Thông tin', 'icon' => 'bi-person-vcard'],
        'activity' => ['label' => 'Nhật ký hoạt động', 'icon' => 'bi-clock-history'],
        'notifications' => ['label' => 'Thông báo đã nhận', 'icon' => 'bi-bell'],
        'devices' => ['label' => 'Thiết bị đăng nhập', 'icon' => 'bi-laptop'],
    ];

    /**
     * Trang chi tiết; tab chọn bằng ?tab= (link thường, chạy không cần JS, giữ được phân trang).
     *
     * @param  Request  $request  ?tab=info|activity|notifications|devices
     * @param  User  $user  tài khoản cần xem
     */
    public function show(Request $request, User $user): View
    {
        Gate::authorize('view', $user);

        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'info';
        $devices = $this->devices($request, $user);

        $data = match ($tab) {
            'activity' => ['logs' => ActivityLog::where('user_id', $user->id)->with('batch:id,name')
                ->latest('id')->paginate(20)->withQueryString()],
            'notifications' => ['notifications' => $user->notifications()->paginate(20)->withQueryString()],
            'devices' => [],
            default => ['stats' => [
                'coordinated' => CookingBatch::where('coordinator_id', $user->id)->count(),
                'tasks' => $user->assignedTasks()->count(),
                'tasksDone' => $user->assignedTasks()->where('status', TaskStatus::Done->value)->count(),
                'batches' => CookingBatch::whereHas('tasks', fn ($q) => $q->where('assignee_id', $user->id))->count(),
                'activities' => ActivityLog::where('user_id', $user->id)->count(),
                'lastActivity' => $devices->max('lastActivity'),
            ]],
        };

        return view('users.show', [
            'user' => $user,
            'tab' => $tab,
            'tabs' => self::TABS,
            'devices' => $devices,
            'sessionTracking' => config('session.driver') === 'database',
            ...$data,
        ]);
    }

    /**
     * Đăng xuất tài khoản khỏi MỘT thiết bị.
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  User  $user  tài khoản
     * @param  string  $device  mã băm SHA-256 của mã phiên
     */
    public function destroyDevice(Request $request, User $user, string $device): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageSessions', $user);

        $session = DB::table('sessions')->where('user_id', $user->id)->pluck('id')
            ->first(fn (string $id) => hash_equals(hash('sha256', $id), $device));
        abort_if($session === null, 404);
        abort_if($session === $request->session()->getId(), 422, 'Đây là phiên bạn đang dùng — hãy dùng nút Đăng xuất.');

        DB::transaction(function () use ($session, $user, $request) {
            DB::table('sessions')->where('id', $session)->delete();
            ActivityLog::record(null, $request->user(), ActivityAction::UserUpdated, 'Đăng xuất 1 thiết bị của tài khoản #'.$user->id.'.');
        });

        return $this->done($request, redirect()->route('users.show', [$user, 'tab' => 'devices']), 'Đã đăng xuất thiết bị.');
    }

    /**
     * Đăng xuất tài khoản khỏi MỌI thiết bị (trừ phiên đang dùng nếu tự xem tài khoản mình),
     * đồng thời vô hiệu "ghi nhớ đăng nhập".
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  User  $user  tài khoản
     */
    public function destroyDevices(Request $request, User $user): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageSessions', $user);

        $count = DB::transaction(function () use ($user, $request) {
            $count = DB::table('sessions')->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())->delete();
            $user->setRememberToken(Str::random(60));
            $user->saveQuietly();
            ActivityLog::record(null, $request->user(), ActivityAction::UserUpdated,
                'Đăng xuất tài khoản #'.$user->id.' khỏi mọi thiết bị ('.$count.' phiên).');

            return $count;
        });

        return $this->done($request, redirect()->route('users.show', [$user, 'tab' => 'devices']),
            "Đã đăng xuất {$count} thiết bị và vô hiệu “ghi nhớ đăng nhập”.");
    }

    /**
     * Phiên đăng nhập hiện có của tài khoản, mới nhất trước.
     *
     * @param  Request  $request  yêu cầu hiện tại (để đánh dấu phiên đang dùng)
     * @param  User  $user  tài khoản
     * @return Collection<int, array{key: string, current: bool, ip: ?string, lastActivity: Carbon, device: array}>
     */
    private function devices(Request $request, User $user): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        $currentId = $request->session()->getId();

        return DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => [
                'key' => hash('sha256', $s->id),
                'current' => $s->id === $currentId,
                'ip' => $s->ip_address,
                'lastActivity' => Carbon::createFromTimestamp($s->last_activity, config('app.timezone')),
                'device' => UserAgent::describe($s->user_agent),
            ]);
    }
}
