<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1b6343">
    <title>{{ isset($title) ? $title.' · ' : '' }}BếpMinhBạch</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('partials.head-assets')
</head>
<body>
<div class="auth-shell">
    <aside class="auth-aside" aria-hidden="true">
        <span class="brand fs-5">
            <span class="brand-mark"><i class="bi bi-egg-fried"></i></span>
            <span>BếpMinhBạch</span>
        </span>
        <div style="max-width: 440px">
            <h2 class="display-6 fw-bold mb-3 text-white">Mỗi đợt nấu, rõ ràng từ đầu đến cuối.</h2>
            <p class="mb-4 opacity-75">Thay sổ tay và tin nhắn rời rạc bằng kế hoạch, checklist và số liệu mọi người cùng xem.</p>
            <div class="auth-feature"><i class="bi bi-list-check"></i><div><strong>Biết việc của mình</strong><div class="opacity-75 small">Mỗi tình nguyện viên thấy ngay việc được giao hôm nay.</div></div></div>
            <div class="auth-feature"><i class="bi bi-basket"></i><div><strong>Không thiếu nguyên liệu</strong><div class="opacity-75 small">Báo thiếu một chạm, điều phối viên thấy cảnh báo tức thì.</div></div></div>
            <div class="auth-feature"><i class="bi bi-bar-chart-line"></i><div><strong>Số liệu minh bạch</strong><div class="opacity-75 small">Đối chiếu suất dự kiến, đã nấu, đã phát và chi phí.</div></div></div>
        </div>
        <p class="small opacity-50 mb-0">Công cụ điều phối nội bộ cho bếp ăn thiện nguyện.</p>
    </aside>
    <main class="auth-main">
        <div class="auth-box">
            <div class="text-center mb-4 d-lg-none">
                <span class="brand fs-4 justify-content-center">
                    <span class="brand-mark"><i class="bi bi-egg-fried" aria-hidden="true"></i></span>
                    <span>Bếp<span class="brand-accent">MinhBạch</span></span>
                </span>
                <p class="text-body-secondary mt-2 mb-0">Điều phối đợt nấu ăn thiện nguyện</p>
            </div>
            {{ $slot }}
        </div>
    </main>
</div>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
