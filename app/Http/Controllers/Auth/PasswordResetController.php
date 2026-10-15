<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quên mật khẩu → email chứa link → đặt mật khẩu mới.
 *
 * - Không tiết lộ email có tài khoản hay không: luôn cùng một thông báo; tài khoản bị khóa không nhận link.
 * - Link dùng một lần, hết hạn sau 60 phút (config/auth.php); mỗi email chỉ yêu cầu lại sau 60 giây;
 *   route giới hạn theo IP (throttle:password-reset).
 * - Đặt lại xong: vô hiệu "ghi nhớ đăng nhập" và mọi phiên khác (auth.session); người dùng đăng nhập lại.
 */
class PasswordResetController extends Controller
{
    /**
     * Form nhập email.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Gửi link đặt lại mật khẩu (nếu email có tài khoản đang hoạt động).
     *
     * @param  Request  $request  email
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']], [], ['email' => 'email']);

        // Mọi kết quả (đã gửi, không có tài khoản, bị khóa, gửi lại quá nhanh trong 60 giây) đều trả CÙNG một
        // thông báo: nếu báo riêng "gửi quá nhanh" thì kẻ xấu gửi 2 lần là biết email có tài khoản.
        // Chống lạm dụng nằm ở giới hạn theo IP (throttle:password-reset) và broker (1 email / 60 giây).
        Password::sendResetLink(['email' => Str::lower($data['email']), 'is_active' => true]);

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }

    /**
     * Form đặt mật khẩu mới (từ link trong email).
     *
     * @param  Request  $request  có email trong query
     * @param  string  $token  token trong link
     */
    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    /**
     * Đặt mật khẩu mới.
     *
     * @param  Request  $request  token, email, mật khẩu mới
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8), 'max:255'],
        ], [], ['email' => 'email', 'password' => 'mật khẩu mới']);

        $status = Password::reset(
            ['email' => Str::lower($data['email']), 'is_active' => true] + $data,
            function (User $user, string $password) {
                $user->password = $password;
                $user->setRememberToken(Str::random(60)); // vô hiệu "ghi nhớ đăng nhập" trên mọi máy
                $user->save();

                event(new PasswordReset($user));
                ActivityLog::record(null, $user, ActivityAction::UserUpdated, 'Tự đặt lại mật khẩu qua email (tài khoản #'.$user->id.').');
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Token sai/hết hạn hoặc email không khớp: cùng một thông báo, không tiết lộ tài khoản.
            throw ValidationException::withMessages(['email' => __(Password::INVALID_TOKEN)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
