<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\Ingredient;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const PERIODS = [7 => '7 ngày qua', 30 => '30 ngày qua', 90 => '90 ngày qua', 365 => '12 tháng qua'];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $days = (int) $request->query('days', 30);
        if (! array_key_exists($days, self::PERIODS)) {
            $days = 30;
        }

        $hasBatches = CookingBatch::exists();

        $upcomingQuery = CookingBatch::whereIn('status', [BatchStatus::Draft->value, BatchStatus::Planned->value])
            ->where('starts_at', '>=', now()->startOfDay());
        $inProgressQuery = CookingBatch::where('status', BatchStatus::InProgress->value);

        $unlockedBatch = fn (Builder $q) => $q->active();
        $openTasksQuery = Task::where('status', '!=', TaskStatus::Done->value)
            ->whereHas('batch', $unlockedBatch)
            ->when(! $user->isCoordinator(), fn ($q) => $q->where('assignee_id', $user->id));

        $periodStart = now()->subDays($days)->startOfDay();
        $periodBatches = CookingBatch::where('status', '!=', BatchStatus::Cancelled->value)
            ->whereBetween('starts_at', [$periodStart, now()])
            ->whereNotNull('distributed_servings');

        return view('dashboard', [
            'hasBatches' => $hasBatches,
            'days' => $days,
            'periods' => self::PERIODS,
            'upcomingCount' => (clone $upcomingQuery)->count(),
            'inProgressCount' => (clone $inProgressQuery)->count(),
            'openTasksCount' => (clone $openTasksQuery)->count(),
            'distributedTotal' => (clone $periodBatches)->sum('distributed_servings'),
            'distributedBatchCount' => (clone $periodBatches)->count(),
            'activeBatches' => CookingBatch::active()
                ->with('coordinator:id,name')
                ->withTaskProgress()
                ->orderByRaw('case when status = ? then 0 else 1 end', [BatchStatus::InProgress->value])
                ->orderBy('starts_at')
                ->limit(6)
                ->get(),
            'myTasks' => Task::where('assignee_id', $user->id)
                ->where('status', '!=', TaskStatus::Done->value)
                ->whereHas('batch', $unlockedBatch)
                ->with('batch:id,name,starts_at,status')
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
            'latestResult' => CookingBatch::where(fn ($q) => $q->whereNotNull('prepared_servings')->orWhereNotNull('distributed_servings'))
                ->orderByDesc('starts_at')
                ->first(),
        ]);
    }
}
