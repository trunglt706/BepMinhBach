<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Lớp cha của mọi controller: phản hồi thống nhất cho form thường và AJAX. */
abstract class Controller
{
    /**
     * Phản hồi sau thao tác thành công.
     * - Gọi bằng AJAX (Accept: application/json): trả JSON, giao diện tự cập nhật vùng dữ liệu, không chuyển trang.
     * - Gửi form thường (JS tắt): redirect kèm thông báo như trước.
     *
     * - $extra['redirect']: chỉ dùng khi trang hiện tại không còn ý nghĩa (vừa tạo/xóa bản ghi);
     *   thông báo được flash để trang đích vẫn hiển thị.
     * - $extra['refresh'] = false: không cần tải lại vùng dữ liệu (thao tác không đổi dữ liệu đang hiển thị).
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  RedirectResponse  $redirect  nơi chuyển tới khi không dùng AJAX
     * @param  string  $message  thông báo thành công (toast hoặc flash)
     * @param  array{keepOpen?: bool, redirect?: string, refresh?: bool}  $extra  tùy chọn cho giao diện AJAX
     */
    protected function done(Request $request, RedirectResponse $redirect, string $message, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            if (isset($extra['redirect'])) {
                $request->session()->flash('success', $message);
            }

            return response()->json(['message' => $message] + $extra);
        }

        return $redirect->with('success', $message);
    }
}
