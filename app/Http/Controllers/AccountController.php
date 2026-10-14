<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $request->user()->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();

        // Đổi mật khẩu không làm thay đổi dữ liệu đang hiển thị: chỉ đóng modal, không tải lại vùng nào.
        return $this->done($request, redirect()->route('dashboard'), 'Đã đổi mật khẩu.', ['refresh' => false]);
    }
}
