<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Quên mật khẩu → email → đặt mật khẩu mới. */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const SENT = 'Nếu email này có tài khoản đang hoạt động, chúng tôi đã gửi link đặt lại mật khẩu.';

    public function test_login_page_links_to_forgot_password_and_pages_are_not_indexed(): void
    {
        $this->get('/login')->assertSee(route('password.request'), false)->assertSee('Quên mật khẩu?');
        $this->get('/forgot-password')->assertOk()->assertSee('Gửi link đặt lại mật khẩu')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/reset-password/abc?email=a@example.test')->assertOk()->assertSee('value="a@example.test"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_reset_link_is_queued_in_vietnamese_and_message_does_not_reveal_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'lan@example.test']);
        $locked = User::factory()->locked()->create(['email' => 'khoa@example.test']);

        foreach (['lan@example.test', 'LAN@example.test', 'khong-co@example.test', 'khoa@example.test'] as $i => $email) {
            if ($i === 1) {
                $this->travel(2)->minutes(); // qua thời gian chờ 60 giây của cùng một email
            }
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertRedirect('/forgot-password')->assertSessionHas('status', fn ($s) => str_starts_with($s, self::SENT));
        }

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 2);
        Notification::assertNotSentTo($locked, ResetPasswordNotification::class); // tài khoản bị khóa không nhận link
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use ($user) {
            $mail = $n->toMail($user);

            return $n instanceof ShouldQueue
                && $mail->subject === 'Đặt lại mật khẩu Góp Gió'
                && str_contains($mail->actionUrl, '/reset-password/'.$n->token.'?email=lan%40example.test');
        });
    }

    public function test_requesting_again_within_a_minute_sends_nothing_and_does_not_reveal_the_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'lan@example.test']);

        $responses = [];
        foreach (['lan@example.test', 'lan@example.test', 'khong-co@example.test', 'khong-co@example.test'] as $email) {
            $responses[] = $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertSessionHasNoErrors()->assertRedirect('/forgot-password')->baseResponse->getSession()->get('status');
        }

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1); // lần 2 trong 60 giây không gửi
        $this->assertCount(1, array_unique($responses), 'Cùng một thông báo cho mọi trường hợp (chống dò email)');
    }

    public function test_user_resets_password_and_other_sessions_and_remember_tokens_are_invalidated(): void
    {
        $user = User::factory()->create(['email' => 'lan@example.test']);
        $oldRemember = 'old-remember-token';
        $user->forceFill(['remember_token' => $oldRemember])->save();
        $token = Password::createToken($user);

        // Mật khẩu xác nhận sai / quá ngắn
        $this->post('/reset-password', ['token' => $token, 'email' => 'lan@example.test', 'password' => 'ngan', 'password_confirmation' => 'khac'])
            ->assertSessionHasErrors('password');

        $this->post('/reset-password', ['token' => $token, 'email' => 'lan@example.test', 'password' => 'mat-khau-moi', 'password_confirmation' => 'mat-khau-moi'])
            ->assertRedirect('/login')->assertSessionHas('status', 'Đã đặt lại mật khẩu. Hãy đăng nhập bằng mật khẩu mới.');

        $user->refresh();
        $this->assertTrue(Hash::check('mat-khau-moi', $user->password));
        $this->assertNotSame($oldRemember, $user->remember_token);
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'user.updated']);

        // Link chỉ dùng một lần
        $this->post('/reset-password', ['token' => $token, 'email' => 'lan@example.test', 'password' => 'mat-khau-khac', 'password_confirmation' => 'mat-khau-khac'])
            ->assertSessionHasErrors(['email' => 'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Hãy yêu cầu link mới.']);

        // Đăng nhập bằng mật khẩu mới được
        $this->post('/login', ['email' => 'lan@example.test', 'password' => 'mat-khau-moi'])->assertRedirect(route('dashboard'));
    }

    public function test_expired_or_foreign_token_and_locked_account_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'lan@example.test']);
        $other = User::factory()->create(['email' => 'binh@example.test']);
        $token = Password::createToken($user);

        // Token của người khác
        $this->post('/reset-password', ['token' => $token, 'email' => 'binh@example.test', 'password' => 'mat-khau-moi', 'password_confirmation' => 'mat-khau-moi'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $other->fresh()->password));

        // Hết hạn sau 60 phút
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['token' => $token, 'email' => 'lan@example.test', 'password' => 'mat-khau-moi', 'password_confirmation' => 'mat-khau-moi'])
            ->assertSessionHasErrors('email');

        // Tài khoản bị khóa: không đặt lại được
        $this->travelBack();
        $token = Password::createToken($user);
        $user->forceFill(['is_active' => false])->save();
        $this->post('/reset-password', ['token' => $token, 'email' => 'lan@example.test', 'password' => 'mat-khau-moi', 'password_confirmation' => 'mat-khau-moi'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_logged_in_user_cannot_open_reset_pages(): void
    {
        $this->actingAs(User::factory()->create())->get('/forgot-password')->assertRedirect(route('dashboard'));
    }
}
