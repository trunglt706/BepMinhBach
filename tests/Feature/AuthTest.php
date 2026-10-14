<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** T01 */
    public function test_guest_is_redirected_to_login_from_internal_pages(): void
    {
        foreach (['/', '/batches', '/cong-viec-cua-toi', '/reports', '/users'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_login_page_renders_in_vietnamese(): void
    {
        $this->get('/login')->assertOk()->assertSee('Đăng nhập')->assertSee('Mật khẩu');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'mat-khau-dung']);

        $this->post('/login', ['email' => $user->email, 'password' => 'mat-khau-dung'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_shows_generic_message_without_revealing_account(): void
    {
        $user = User::factory()->create(['password' => 'mat-khau-dung']);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'sai'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);

        $this->from('/login')->post('/login', ['email' => 'khongton@example.com', 'password' => 'sai'])
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        $this->assertGuest();
    }

    /** T02 */
    public function test_locked_account_cannot_login(): void
    {
        $user = User::factory()->locked()->create(['password' => 'mat-khau-dung']);

        $this->post('/login', ['email' => $user->email, 'password' => 'mat-khau-dung'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** T02 — phiên đăng nhập còn hiệu lực cũng bị chặn ngay khi tài khoản bị khóa. */
    public function test_locked_account_with_active_session_is_logged_out(): void
    {
        $user = User::factory()->coordinator()->create();
        $this->actingAs($user)->get('/')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/batches')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'mat-khau-dung']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'sai']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'mat-khau-dung'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create(['password' => 'mat-khau-cu']);

        $this->actingAs($user)->put('/tai-khoan/mat-khau', [
            'current_password' => 'sai',
            'password' => 'mat-khau-moi-123',
            'password_confirmation' => 'mat-khau-moi-123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/tai-khoan/mat-khau', [
            'current_password' => 'mat-khau-cu',
            'password' => 'mat-khau-moi-123',
            'password_confirmation' => 'mat-khau-moi-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(password_verify('mat-khau-moi-123', $user->fresh()->password));
    }
}
