<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Đếm lượt xem bài viết, chống tăng ảo:
 * - mỗi người xem (IP + trình duyệt) chỉ tính 1 lần / bài trong `bmb.post_view_window_minutes`;
 * - không tính bot/trình thu thập, bản xem trước (bài chưa đăng), người quản lý bài viết;
 * - trang công khai còn có giới hạn tần suất theo IP (throttle:public).
 *
 * Quyền riêng tư: khóa nhận diện là HMAC(IP + User-Agent) với APP_KEY, chỉ nằm trong cache và tự hết hạn;
 * không lưu IP hay danh sách người xem.
 */
class PostViewCounter
{
    /** Dấu hiệu User-Agent của bot, trình xem trước liên kết, công cụ dòng lệnh. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embed|curl|wget|python|httpclient|headless|lighthouse/i';

    /**
     * Tăng lượt xem nếu yêu cầu này được tính.
     *
     * @param  Post  $post  bài đang xem (đã đăng)
     * @param  Request  $request  yêu cầu của người xem
     * @return bool true nếu đã tăng lượt xem
     */
    public function record(Post $post, Request $request): bool
    {
        if (! $this->countable($post, $request)) {
            return false;
        }

        $visitor = hash_hmac('sha256', $request->ip().'|'.$request->userAgent(), (string) config('app.key'));
        $minutes = max(1, (int) config('bmb.post_view_window_minutes', 360));

        // Cache::add là thao tác nguyên tử: chỉ yêu cầu đầu tiên trong khoảng thời gian thêm được khóa.
        if (! Cache::add("post-view:{$post->id}:{$visitor}", 1, now()->addMinutes($minutes))) {
            return false;
        }

        // Query builder (không qua Eloquent) để không đổi updated_at của bài.
        DB::table($post->getTable())->where('id', $post->id)->increment('view_count');
        $post->view_count++;

        return true;
    }

    /**
     * Yêu cầu có được tính lượt xem không (bài đã đăng, không phải bot, không phải người quản lý bài).
     *
     * @param  Post  $post  bài đang xem
     * @param  Request  $request  yêu cầu của người xem
     */
    private function countable(Post $post, Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $post->isPublished()
            && $agent !== ''
            && ! preg_match(self::BOT_PATTERN, $agent)
            && ! $request->user()?->can('update', $post);
    }
}
