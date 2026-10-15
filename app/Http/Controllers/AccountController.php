<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Tài khoản của chính người đang đăng nhập. */
class AccountController extends Controller
{
    /**
     * Form đổi mật khẩu (trang đầy đủ hoặc modal).
     */
    public function editPassword(): View
    {
        return view('account.password');
    }

    /**
     * Đổi mật khẩu rồi tạo lại session id (chống chiếm phiên). Không ghi mật khẩu vào log.
     *
     * @param  PasswordUpdateRequest  $request  mật khẩu hiện tại + mật khẩu mới đã kiểm tra
     */
    public function updatePassword(PasswordUpdateRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        // Máy đang dùng có "Ghi nhớ đăng nhập" thì cấp lại cookie mới (cookie cũ chứa mã băm mật khẩu cũ nên hết hiệu lực).
        $remembered = $request->cookies->has(Auth::guard()->getRecallerName());

        $user->password = $request->validated('password');
        $user->setRememberToken(Str::random(60)); // vô hiệu "ghi nhớ đăng nhập" trên các máy khác
        $user->save();

        // Phiên hiện tại giữ đăng nhập; phiên khác bị đăng xuất bởi middleware auth.session.
        Auth::login($user, $remembered);
        $request->session()->regenerate();

        // Đổi mật khẩu không làm thay đổi dữ liệu đang hiển thị: chỉ đóng modal, không tải lại vùng nào.
        return $this->done($request, redirect()->route('dashboard'), 'Đã đổi mật khẩu.', ['refresh' => false]);
    }
}
