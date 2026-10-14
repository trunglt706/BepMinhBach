<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật cho mọi phản hồi của ứng dụng.
 *
 * - CSP: chỉ chạy JS từ chính site (mọi thư viện nằm trong public/vendor, không dùng CDN), không nhúng plugin,
 *   không cho site khác nhúng trang vào iframe, form chỉ gửi về chính site.
 *   style 'unsafe-inline' vì view dùng thuộc tính style cho biểu đồ/thanh tiến độ (không chạy được mã).
 * - HSTS chỉ gửi khi yêu cầu đi qua HTTPS (bật trên máy chạy HTTP thì trình duyệt sẽ không vào được).
 */
class SecurityHeaders
{
    /** Chính sách nội dung: ảnh blob:/data: cho xem trước và thu nhỏ ảnh trước khi tải lên. */
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; media-src 'self'; "
        ."object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    /**
     * Gắn header bảo mật nếu phản hồi chưa tự đặt.
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  Closure(Request): Response  $next  middleware tiếp theo
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $defaults = [
            'Content-Security-Policy' => self::CSP,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($defaults as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
