<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Kiểm tra nhanh cấu hình trước/sau khi đưa lên máy chủ thật. Chỉ đọc, không sửa gì.
 * Không in giá trị bí mật (APP_KEY, mật khẩu DB) — chỉ báo có/không.
 */
class CheckProduction extends Command
{
    /** @var string */
    protected $signature = 'bmb:check-production';

    /** @var string */
    protected $description = 'Kiểm tra cấu hình an toàn cho môi trường thật (chỉ đọc)';

    /**
     * Chạy các mục kiểm tra; trả mã 1 nếu có mục LỖI (để dùng trong script triển khai).
     */
    public function handle(): int
    {
        $results = $this->checks();

        $this->table(['', 'Mục kiểm tra', 'Ghi chú'], array_map(
            fn (array $r) => [$r[0], $r[1], $r[2]],
            $results,
        ));

        $failed = count(array_filter($results, fn (array $r) => $r[0] === 'LỖI'));
        $warned = count(array_filter($results, fn (array $r) => $r[0] === 'CHÚ Ý'));

        if ($failed > 0) {
            $this->error("Có {$failed} lỗi cần sửa trước khi cho người dùng truy cập.");

            return self::FAILURE;
        }

        $this->info($warned > 0 ? "Không có lỗi; {$warned} mục cần xem lại." : 'Cấu hình đạt mọi mục kiểm tra.');

        return self::SUCCESS;
    }

    /**
     * Danh sách kết quả [trạng thái, mục, ghi chú]; trạng thái là OK | CHÚ Ý | LỖI.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function checks(): array
    {
        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');

        $r = [];
        $r[] = $this->row(app()->environment('production'), 'APP_ENV=production', 'Đang là "'.app()->environment().'"');
        $r[] = $this->row(! config('app.debug'), 'APP_DEBUG=false', 'Bật debug sẽ lộ mã nguồn, cấu hình khi có lỗi');
        $r[] = $this->row(filled(config('app.key')), 'APP_KEY đã tạo', 'Chạy: php artisan key:generate');
        $r[] = $this->row($https, 'APP_URL dùng https://', 'Mật khẩu và phiên đăng nhập đi qua mạng không mã hóa', warnOnly: true);
        $r[] = $https
            ? $this->row((bool) config('session.secure'), 'Cookie phiên chỉ gửi qua HTTPS', 'Đặt SESSION_SECURE_COOKIE=true')
            : ['CHÚ Ý', 'Cookie phiên chỉ gửi qua HTTPS', 'Không áp dụng khi chưa dùng HTTPS'];
        $r[] = $this->row(config('session.http_only'), 'Cookie phiên HttpOnly', 'Không đặt SESSION_HTTP_ONLY=false');
        $r[] = $this->row(! in_array(config('logging.channels.single.level', 'debug'), ['debug'], true), 'LOG_LEVEL không phải debug',
            'Đặt LOG_LEVEL=warning để log gọn, ít dữ liệu', warnOnly: true);

        $r[] = $this->row(is_writable(storage_path('framework')) && is_writable(storage_path('logs')), 'storage/ ghi được', 'Cấp quyền ghi cho storage/ và bootstrap/cache/');
        $r[] = $this->row(is_writable(base_path('bootstrap/cache')), 'bootstrap/cache ghi được', 'Cấp quyền ghi');
        $r[] = $this->row(is_file(public_path('uploads/.htaccess')), 'public/uploads/.htaccess có mặt', 'Thiếu file chặn chạy mã trong thư mục ảnh tải lên');
        $r[] = $this->row(app()->configurationIsCached(), 'Đã cache cấu hình', 'Chạy: php artisan config:cache (sau mỗi lần sửa .env)', warnOnly: true);

        try {
            DB::connection()->getPdo();
            $r[] = ['OK', 'Kết nối cơ sở dữ liệu', DB::connection()->getDriverName()];
        } catch (Throwable) {
            $r[] = ['LỖI', 'Kết nối cơ sở dữ liệu', 'Không kết nối được; kiểm tra DB_* trong .env'];

            return $r;
        }

        try {
            $demo = User::where('email', 'like', '%@bepminhbach.test')->where('is_active', true)->count();
            $r[] = $this->row($demo === 0, 'Không còn tài khoản demo', "{$demo} tài khoản @bepminhbach.test đang hoạt động — khóa hoặc xóa");
            $admins = User::where('role', Role::Admin->value)->where('is_active', true)->count();
            $r[] = $this->row($admins > 0, 'Có quản trị viên đang hoạt động', 'Chạy: php artisan bmb:create-admin');
        } catch (Throwable) {
            $r[] = ['LỖI', 'Bảng dữ liệu', 'Chưa chạy migration: php artisan migrate --force'];
        }

        return $r;
    }

    /**
     * Tạo một dòng kết quả.
     *
     * @param  bool  $ok  điều kiện đạt
     * @param  string  $label  tên mục
     * @param  string  $hint  hướng dẫn khi không đạt
     * @param  bool  $warnOnly  không đạt chỉ là CHÚ Ý (không chặn)
     * @return array{0: string, 1: string, 2: string}
     */
    private function row(bool $ok, string $label, string $hint, bool $warnOnly = false): array
    {
        if ($ok) {
            return ['OK', $label, ''];
        }

        return [$warnOnly ? 'CHÚ Ý' : 'LỖI', $label, $hint];
    }
}
