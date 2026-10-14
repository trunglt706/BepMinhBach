<?php

namespace App\Providers;

use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\PublicPostCache;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/** Cấu hình chung của ứng dụng: phân trang, ngôn ngữ ngày, Gate báo cáo, giới hạn tần suất, số việc trên menu. */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký service (chưa cần: các lớp đều tự resolve qua container).
     */
    public function register(): void
    {
        //
    }

    /**
     * Khởi tạo khi ứng dụng chạy.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale(config('app.locale'));

        // Báo cáo nội bộ: người ghi nhận kết quả trở lên.
        Gate::define('viewReports', fn (User $user) => $user->isRecorder());

        $this->configureRateLimiting();
        $this->configurePublicCache();

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

    /**
     * Mọi thay đổi ảnh hưởng trang công khai (bài viết, ảnh, thẻ, thông tin đợt nấu) làm mới bộ nhớ đệm bài viết.
     * Gắn thẻ dùng sync() (không có sự kiện riêng) nhưng luôn đi kèm lưu bài trong cùng giao dịch.
     */
    private function configurePublicCache(): void
    {
        foreach ([Post::class, PostImage::class, Tag::class, CookingBatch::class] as $model) {
            $model::saved(fn () => PublicPostCache::invalidate());
            $model::deleted(fn () => PublicPostCache::invalidate());
        }
    }

    /**
     * Chống spam theo TỪNG tài khoản (không theo IP: nhiều tình nguyện viên có thể dùng chung wifi của bếp).
     * Thao tác ghi bị giới hạn chặt; mở trang thì rộng rãi. Trang công khai giới hạn theo IP.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('user-actions', function (Request $request) {
            $who = $request->user() ? 'u'.$request->user()->id : 'ip'.$request->ip();

            return $request->isMethodSafe()
                ? Limit::perMinute(config('bmb.rate_limits.reads'))->by('read:'.$who)->response($this->tooManyRequests(...))
                : Limit::perMinute(config('bmb.rate_limits.writes'))->by('write:'.$who)->response($this->tooManyRequests(...));
        });

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(config('bmb.rate_limits.public'))
            ->by('public:'.$request->ip())
            ->response($this->tooManyRequests(...)));
    }

    /**
     * Phản hồi 429 tiếng Việt: JSON cho AJAX, trang 429 cho GET, quay lại form (giữ dữ liệu đã nhập,
     * trừ mật khẩu) khi gửi form thường.
     *
     * @param  Request  $request  yêu cầu bị chặn
     * @param  array<string, string|int>  $headers  header giới hạn (Retry-After, X-RateLimit-*)
     */
    private function tooManyRequests(Request $request, array $headers): Response
    {
        $seconds = (int) ($headers['Retry-After'] ?? 60);
        $message = 'Bạn thao tác quá nhanh. Vui lòng đợi '.$seconds.' giây rồi thử lại.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429, $headers);
        }
        if ($request->isMethodSafe()) {
            return response()->view('errors.429', ['retryAfter' => $seconds], 429, $headers);
        }

        return back()->with('error', $message)
            ->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']));
    }
}
