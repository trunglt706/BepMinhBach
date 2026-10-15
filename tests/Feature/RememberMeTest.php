<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** "Ghi nhớ đăng nhập": cookie nhớ đăng nhập thật sự giữ phiên, và bị vô hiệu khi đổi mật khẩu / khóa tài khoản. */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Đăng nhập (có/không ghi nhớ) và trả về cookie nhớ đăng nhập (null nếu không có).
     */
    private function login(User $user, bool $remember): ?string
    {
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => $remember ? '1' : null]);
        $response->assertRedirect(route('dashboard'));
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === Auth::guard()->getRecallerName());

        return $cookie?->getValue() ? CookieValuePrefix::remove(decrypt($cookie->getValue(), false)) : null;
    }

    /** Giả lập trình duyệt mở lại sau khi phiên hết hạn: chỉ còn cookie nhớ đăng nhập. */
    private function freshVisit(?string $recaller)
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $request = $this;
        if ($recaller) {
            $request = $request->withCookie(Auth::guard()->getRecallerName(), $recaller);
        }

        return $request->get('/admin');
    }

    public function test_remember_cookie_keeps_user_logged_in_after_session_expires(): void
    {
        $user = User::factory()->create();

        $recaller = $this->login($user, true);
        $this->assertNotNull($recaller, 'Có cookie nhớ đăng nhập');
        $this->assertNotNull($user->fresh()->remember_token);

        $this->freshVisit($recaller)->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_without_remember_there_is_no_cookie_and_expired_session_requires_login(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->login($user, false));
        $this->freshVisit(null)->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_changing_password_invalidates_remember_cookie_on_other_devices(): void
    {
        $user = User::factory()->create();
        $otherDevice = $this->login($user, true);

        // Đổi mật khẩu từ một máy khác
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'password', 'password' => 'mat-khau-moi-123', 'password_confirmation' => 'mat-khau-moi-123',
        ])->assertRedirect();

        $this->freshVisit($otherDevice)->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_locking_account_invalidates_remember_cookie(): void
    {
        $user = User::factory()->create();
        $recaller = $this->login($user, true);

        $admin = User::factory()->admin()->create();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->post("/admin/users/{$user->id}/toggle-active")->assertRedirect();

        $this->freshVisit($recaller)->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_other_sessions_are_logged_out_after_password_change_but_current_one_stays(): void
    {
        $user = User::factory()->create();

        // Phiên trên máy khác đã lưu mã băm của mật khẩu cũ (auth.session)
        $this->actingAs($user)->get('/admin')->assertOk();
        $otherSessionHash = session('password_hash_web');
        $this->assertNotNull($otherSessionHash);

        // Đổi mật khẩu: phiên hiện tại vẫn dùng tiếp
        $this->put('/admin/account/password', [
            'current_password' => 'password', 'password' => 'mat-khau-moi-123', 'password_confirmation' => 'mat-khau-moi-123',
        ])->assertRedirect();
        $this->get('/admin')->assertOk();

        // Phiên còn giữ mã băm cũ bị đăng xuất
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $otherSessionHash])->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }
}
