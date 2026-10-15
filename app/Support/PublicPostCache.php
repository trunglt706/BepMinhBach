<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bộ nhớ đệm cho trang bài viết công khai (dữ liệu bài, danh sách, bộ lọc).
 *
 * Vô hiệu theo "phiên bản": mọi khóa có chứa số phiên bản hiện tại; khi bài viết, ảnh, thẻ hoặc chương trình
 * thay đổi thì tăng phiên bản (sau khi giao dịch DB thành công) → mọi khóa cũ tự bị bỏ qua và hết hạn theo TTL.
 * Lượt xem tăng bằng query builder nên không làm mất bộ nhớ đệm.
 */
class PublicPostCache
{
    /** Khóa lưu số phiên bản. */
    private const VERSION_KEY = 'public-posts:version';

    /** Thời gian giữ một mục (giây). Bài hẹn giờ đăng sẽ xuất hiện chậm nhất sau khoảng này. */
    public const TTL = 600;

    /**
     * Lấy từ bộ nhớ đệm hoặc tính rồi lưu.
     *
     * @template T
     *
     * @param  string  $key  khóa riêng của dữ liệu (không gồm phiên bản)
     * @param  Closure(): T  $callback  hàm tạo dữ liệu khi chưa có trong bộ nhớ đệm
     * @return T
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember('public-posts:v'.self::version().':'.$key, self::TTL, $callback);
    }

    /**
     * Đánh dấu dữ liệu công khai đã đổi. Trong giao dịch: chờ giao dịch thành công mới tăng phiên bản,
     * để yêu cầu khác không kịp lưu lại dữ liệu cũ dưới phiên bản mới.
     */
    public static function invalidate(): void
    {
        DB::afterCommit(function () {
            if (! Cache::add(self::VERSION_KEY, 2)) {
                Cache::increment(self::VERSION_KEY);
            }
        });
    }

    /**
     * Phiên bản hiện tại (1 nếu chưa có).
     */
    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
