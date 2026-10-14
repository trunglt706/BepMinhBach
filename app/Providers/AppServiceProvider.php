<?php

namespace App\Providers;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Models\ContactMessage;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\PublicPostCache;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
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

        // Chạy sau proxy/CDN: tin proxy đã khai báo để lấy đúng IP khách (giới hạn theo IP) và nhận biết HTTPS.
        if ($proxies = config('bmb.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Báo cáo nội bộ: người ghi nhận kết quả trở lên.
        Gate::define('viewReports', fn (User $user) => $user->isRecorder());

        $this->configureRateLimiting();
        $this->configurePublicCache();

        // Badge trên menu: việc của tôi hôm nay, đợt đang diễn ra, liên hệ chưa xử lý.
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            // Việc của tôi HÔM NAY: chưa xong, thuộc đợt chưa khóa, và có hạn trong hôm nay
            // (hoặc không có hạn nhưng đợt diễn ra hôm nay).
            $today = today();
            $view->with('navTodayTasks', $user
                ? Task::where('assignee_id', $user->id)
                    ->where('status', '!=', TaskStatus::Done->value)
                    ->whereHas('batch', fn ($q) => $q->active())
                    ->where(fn ($q) => $q->whereBetween('due_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                        ->orWhere(fn ($q) => $q->whereNull('due_at')
                            ->whereHas('batch', fn ($b) => $b->where('starts_on', $today->toDateString()))))
                    ->count()
                : 0);
            // Số đợt đang diễn ra (mọi người dùng nội bộ đều thấy).
            $view->with('navLiveBatches', $user
                ? CookingBatch::where('status', BatchStatus::InProgress->value)->count()
                : 0);
            // Số liên hệ chưa xử lý — chỉ đếm cho người được xem liên hệ.
            $view->with('navNewContacts', $user && $user->can('viewAny', ContactMessage::class)
                ? ContactMessage::unhandled()->count()
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

        // Gửi liên hệ: tính cả lần gửi lỗi (chống dò/spam form). Giới hạn 1 liên hệ / ngày nằm ở ContactRequest.
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinutes(10, config('bmb.rate_limits.contact'))
            ->by('contact:'.$request->ip())
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
