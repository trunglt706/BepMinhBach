<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Đăng xuất ngay tài khoản đã bị khóa, kể cả khi phiên đăng nhập vẫn còn. */
class EnsureUserIsActive
{
    /**
     * Tài khoản bị khóa: hủy phiên và chuyển về trang đăng nhập kèm thông báo.
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  Closure(Request): Response  $next  middleware tiếp theo
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.']);
        }

        return $next($request);
    }
}
