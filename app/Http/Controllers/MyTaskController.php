<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Công việc của tôi": các việc được giao cho người đang đăng nhập. */
class MyTaskController extends Controller
{
    /**
     * Việc của tôi: quá hạn + hôm nay lên đầu, sau đó việc sắp tới, cuối cùng việc đã xong gần đây.
     *
     * @param  Request  $request  yêu cầu hiện tại (lấy người dùng)
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $open = Task::where('assignee_id', $user->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereHas('batch', fn ($q) => $q->active())
            ->with('batch:id,name,starts_on,status,location_text')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(100)
            ->get();

        $endOfToday = now()->endOfDay();
        [$urgent, $later] = $open->partition(fn (Task $t) => $t->due_at !== null && $t->due_at->lte($endOfToday)
            || $t->status === TaskStatus::Blocked);

        $recentlyDone = Task::where('assignee_id', $user->id)
            ->where('status', TaskStatus::Done->value)
            ->where('completed_at', '>=', now()->subDays(7))
            ->with('batch:id,name')
            ->latest('completed_at')
            ->limit(10)
            ->get();

        return view('tasks.mine', compact('urgent', 'later', 'recentlyDone'));
    }
}
