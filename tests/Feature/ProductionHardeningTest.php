<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** BM-051: header bảo mật, cookie phiên, lệnh kiểm tra cấu hình production. */
class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent_on_public_internal_and_error_pages(): void
    {
        $user = User::factory()->create();

        $responses = [
            $this->get('/login'),
            $this->get('/'),
            $this->actingAs($user)->get('/admin'),
            $this->get('/bai-viet/khong-ton-tai'),
        ];

        foreach ($responses as $response) {
            $response->assertHeader('Content-Security-Policy', SecurityHeaders::CSP)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('Strict-Transport-Security'); // HTTP thường: không gửi HSTS
        }
    }

    public function test_csp_only_allows_own_scripts_and_blocks_framing_by_other_sites(): void
    {
        $this->assertStringContainsString("script-src 'self';", SecurityHeaders::CSP);
        $this->assertStringNotContainsString('unsafe-eval', SecurityHeaders::CSP);
        $this->assertStringContainsString("frame-ancestors 'self'", SecurityHeaders::CSP);
        $this->assertStringContainsString("object-src 'none'", SecurityHeaders::CSP);
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_views_have_no_inline_scripts_or_event_handlers_blocked_by_csp(): void
    {
        $offending = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $html = (string) file_get_contents($file->getPathname());
            // <script> không có src và không phải dữ liệu JSON-LD → bị CSP chặn
            if (preg_match('/<script(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)[^>]*>/i', $html)
                || preg_match('/\son(click|change|submit|load|input)=/i', $html)
                || str_contains($html, 'javascript:')) {
                $offending[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offending, 'Mã JS nội tuyến sẽ bị Content-Security-Policy chặn.');
    }

    public function test_session_cookie_is_http_only(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->get('/login')->assertCookie(config('session.cookie'));
    }

    public function test_check_production_fails_on_local_debug_configuration(): void
    {
        config(['app.debug' => true]);

        $this->artisan('bmb:check-production')
            ->expectsOutputToContain('APP_DEBUG=false')
            ->expectsOutputToContain('lỗi cần sửa')
            ->assertExitCode(1);
    }

    public function test_check_production_flags_active_demo_accounts_and_missing_admin(): void
    {
        User::factory()->create(['email' => 'tnv1@gopgio.test', 'is_active' => true]);

        $this->artisan('bmb:check-production')
            ->expectsOutputToContain('1 tài khoản @gopgio.test')
            ->expectsOutputToContain('bmb:create-admin')
            ->assertExitCode(1);
    }

    public function test_create_admin_runs_non_interactively_with_password_from_environment(): void
    {
        putenv('BMB_ADMIN_PASSWORD=mat-khau-rat-dai-123');
        try {
            $this->artisan('bmb:create-admin', ['--name' => 'Quản trị VPS', '--email' => 'QuanTri@Example.org', '--no-interaction' => true])
                ->expectsOutputToContain('Đã tạo tài khoản quản trị')
                ->assertExitCode(0);

            $admin = User::where('email', 'quantri@example.org')->sole();
            $this->assertSame('Quản trị VPS', $admin->name);
            $this->assertTrue($admin->isAdmin());
            $this->assertTrue(Hash::check('mat-khau-rat-dai-123', $admin->password));

            // Mật khẩu ngắn bị từ chối, không tạo tài khoản
            putenv('BMB_ADMIN_PASSWORD=ngan');
            $this->artisan('bmb:create-admin', ['--name' => 'X', '--email' => 'x@example.org', '--no-interaction' => true])->assertExitCode(1);
            $this->assertNull(User::where('email', 'x@example.org')->first());
        } finally {
            putenv('BMB_ADMIN_PASSWORD');
        }
    }

    public function test_deploy_script_is_valid_bash_with_lf_line_endings(): void
    {
        $script = (string) file_get_contents(base_path('deploy/deploy.sh'));

        $this->assertStringStartsWith("#!/usr/bin/env bash\n", $script);
        $this->assertStringNotContainsString("\r", $script);
        $this->assertStringContainsString('set -Eeuo pipefail', $script);
        // Không bao giờ chạy seeder demo trên máy chủ thật
        $this->assertStringNotContainsString('db:seed', $script);
        // Chỉ cập nhật code: không cài gói, không sửa .env / cấu hình hệ thống
        foreach (['apt-get', 'set_env', 'certbot', 'ufw', '/etc/nginx', 'key:generate'] as $systemStep) {
            $this->assertStringNotContainsString($systemStep, $script, "deploy.sh không được chứa bước hệ thống: {$systemStep}");
        }
        // Các bước cập nhật theo đúng thứ tự; lỗi thì tắt bảo trì
        $this->assertMatchesRegularExpression('/artisan down.*git -C "\$APP_DIR" fetch.*pull --ff-only.*composer install --no-dev'
            .'.*npm ci.*chown -R.*migrate --force.*config:cache.*artisan up.*systemctl reload.*queue:restart.*bmb:check-production/s', $script);
        $this->assertStringContainsString("trap 'on_error \$LINENO' ERR", $script);
    }

    public function test_check_production_passes_with_safe_configuration(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.url' => 'https://bep.example.org',
            'session.secure' => true,
            'logging.channels.single.level' => 'warning',
        ]);
        User::factory()->admin()->create(['email' => 'quantri@example.org']);

        $this->artisan('bmb:check-production')
            ->doesntExpectOutputToContain('LỖI')
            ->assertExitCode(0);
    }
}
