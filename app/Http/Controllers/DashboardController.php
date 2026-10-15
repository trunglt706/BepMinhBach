<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\DashboardRequest;
use App\Models\CookingBatch;
use App\Models\Ingredient;
use App\Models\Task;
use App\Models\User;
use App\Support\Charts;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/** Trang tổng quan: số liệu nhanh, biểu đồ theo khoảng thời gian, việc cần xử lý, chương trình đang mở, việc của tôi. */
class DashboardController extends Controller
{
    /**
     * Trang tổng quan.
     *
     * @param  DashboardRequest  $request  khoảng thời gian đã kiểm tra (?days=)
     */
    public function __invoke(DashboardRequest $request): View
    {
        $user = $request->user();
        $days = $request->days();

        $hasBatches = CookingBatch::visibleTo($user)->exists();

        $upcomingQuery = CookingBatch::visibleTo($user)->whereIn('status', [BatchStatus::Draft->value, BatchStatus::Planned->value])
            ->where('starts_on', '>=', today()->toDateString());
        $inProgressQuery = CookingBatch::visibleTo($user)->where('status', BatchStatus::InProgress->value);

        $unlockedBatch = fn (Builder $q) => $q->active()->visibleTo($user); // chỉ chương trình trong phạm vi được xem
        $openTasksQuery = Task::where('status', '!=', TaskStatus::Done->value)
            ->whereHas('batch', $unlockedBatch)
            ->when(! $user->isCoordinator(), fn ($q) => $q->where('assignee_id', $user->id));

        $periodStart = now()->subDays($days)->startOfDay();
        $periodBatches = CookingBatch::visibleTo($user)->where('status', '!=', BatchStatus::Cancelled->value)
            ->whereBetween('starts_on', [$periodStart->toDateString(), today()->toDateString()])
            ->whereNotNull('distributed_servings');

        return view('dashboard', [
            ...$this->charts($user, $periodStart),
            'hasBatches' => $hasBatches,
            'days' => $days,
            'periods' => DashboardRequest::PERIODS,
            'upcomingCount' => (clone $upcomingQuery)->count(),
            'inProgressCount' => (clone $inProgressQuery)->count(),
            'openTasksCount' => (clone $openTasksQuery)->count(),
            'distributedTotal' => (clone $periodBatches)->sum('distributed_servings'),
            'distributedBatchCount' => (clone $periodBatches)->count(),
            'activeBatches' => CookingBatch::visibleTo($user)->active()
                ->with('coordinator:id,name')
                ->withTaskProgress()
                ->orderByRaw('case when status = ? then 0 else 1 end', [BatchStatus::InProgress->value])
                ->orderBy('starts_on')
                ->limit(6)
                ->get(),
            'myTasks' => Task::where('assignee_id', $user->id)
                ->where('status', '!=', TaskStatus::Done->value)
                ->whereHas('batch', $unlockedBatch)
                ->with('batch:id,name,starts_on,status')
                ->orderByRaw('due_at is null')
                ->orderBy('due_at')
                ->limit(5)
                ->get(),
            'shortages' => Ingredient::where('needs_restock', true)
                ->whereHas('batch', $unlockedBatch)
                ->with('batch:id,name')
                ->latest('restock_reported_at')
                ->limit(10)
                ->get(),
            'blockedTasks' => Task::where('status', TaskStatus::Blocked->value)
                ->whereHas('batch', $unlockedBatch)
                ->with(['batch:id,name', 'assignee:id,name'])
                ->latest('status_updated_at')
                ->limit(10)
                ->get(),
            'latestResult' => CookingBatch::visibleTo($user)->where(fn ($q) => $q->whereNotNull('prepared_servings')->orWhereNotNull('distributed_servings'))
                ->orderByDesc('starts_on')
                ->first(),
        ]);
    }

    /**
     * Số liệu cho biểu đồ, cùng khoảng thời gian với bộ chọn phía trên.
     * Chỉ là số tổng hợp theo chương trình, không có thông tin người nhận. Chi phí chỉ dành cho người xem được báo cáo.
     *
     * @param  User  $user  người đang xem (quyết định có biểu đồ chi phí hay không)
     * @param  CarbonInterface  $from  đầu khoảng thời gian
     * @return array{servingsChart: array, statusChart: list<array>, expenseChart: ?array} biến cho view
     */
    private function charts(User $user, CarbonInterface $from): array
    {
        $to = now();

        // So sánh dự kiến với phân phát chỉ trên các chương trình đã nhập số phân phát, để "chưa nhập" không bị hiểu là thiếu suất.
        $withResults = CookingBatch::visibleTo($user)->where('status', '!=', BatchStatus::Cancelled->value)
            ->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('distributed_servings')
            ->get(['id', 'starts_on', 'expected_servings', 'distributed_servings']);

        // Trạng thái: các chương trình bắt đầu từ đầu khoảng thời gian, gồm cả chương trình sắp tới.
        $statusCounts = CookingBatch::visibleTo($user)->where('starts_on', '>=', $from->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $expenseChart = null;
        if ($user->can('viewReports')) {
            $withExpenses = CookingBatch::visibleTo($user)->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()])
                ->whereHas('expenseItems')
                ->withSum('expenseItems', 'amount_vnd')
                ->get(['id', 'starts_on']);
            $expenseChart = [
                'groups' => Charts::groupByTime($withExpenses, fn ($b) => $b->starts_on,
                    ['amount' => fn ($b) => (int) $b->expense_items_sum_amount_vnd], $from, $to),
                'batchCount' => $withExpenses->count(),
            ];
        }

        return [
            'servingsChart' => [
                'groups' => Charts::groupByTime($withResults, fn ($b) => $b->starts_on, [
                    'expected' => fn ($b) => $b->expected_servings,
                    'distributed' => fn ($b) => $b->distributed_servings,
                ], $from, $to),
                'batchCount' => $withResults->count(),
            ],
            'statusChart' => collect(BatchStatus::cases())->map(fn (BatchStatus $s) => [
                'label' => $s->label(),
                'icon' => $s->icon(),
                'value' => (int) ($statusCounts[$s->value] ?? 0),
                'tone' => 'primary',
            ])->all(),
            'expenseChart' => $expenseChart,
        ];
    }
}
