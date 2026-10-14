<?php

namespace App\Providers;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale(config('app.locale'));

        // Báo cáo nội bộ: người ghi nhận kết quả trở lên.
        Gate::define('viewReports', fn (User $user) => $user->isRecorder());

        // Số việc đang mở của người dùng — hiển thị trên menu "Công việc của tôi".
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('navOpenTasks', $user
                ? Task::where('assignee_id', $user->id)
                    ->where('status', '!=', TaskStatus::Done->value)
                    ->whereHas('batch', fn ($q) => $q->active())
                    ->count()
                : 0);
        });
    }
}
