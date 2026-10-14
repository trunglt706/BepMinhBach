<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function editPassword(): View
    {
        return view('account.password');
    }

    public function updatePassword(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $validated['password']]);
        $request->session()->regenerate();

        // Đổi mật khẩu không làm thay đổi dữ liệu đang hiển thị: chỉ đóng modal, không tải lại vùng nào.
        return $this->done($request, redirect()->route('dashboard'), 'Đã đổi mật khẩu.', ['refresh' => false]);
    }
}
