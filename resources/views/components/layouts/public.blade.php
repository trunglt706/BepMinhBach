@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false])
{{--
    Khung trang công khai (khách không cần đăng nhập). Không hiện thông tin nội bộ của tài khoản đang đăng nhập.
    SEO: description, canonical, robots (noindex cho trang tìm kiếm/lọc và bản xem trước), slot $head cho
    Open Graph / dữ liệu có cấu trúc riêng từng trang.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1b6343">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}BếpMinhBạch</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif
    <meta name="robots" content="{{ $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large' }}">
    @if($canonical)<link rel="canonical" href="{{ $canonical }}">@endif
    <meta property="og:site_name" content="BếpMinhBạch">
    <meta property="og:locale" content="vi_VN">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">
    @include('partials.head-assets')
    {{ $head ?? '' }}
</head>
<body class="public-site">
<a class="visually-hidden-focusable btn btn-light position-fixed top-0 start-0 m-2" style="z-index: 2000" href="#main">Bỏ qua điều hướng</a>

<header class="public-header d-print-none">
    <div class="container-xxl d-flex align-items-center gap-3">
        <a class="brand" href="{{ route('blog.index') }}">
            <span class="brand-mark"><i class="bi bi-egg-fried" aria-hidden="true"></i></span>
            <span>Bếp<span class="brand-accent">MinhBạch</span></span>
        </a>
        <nav class="public-nav me-auto" aria-label="Điều hướng công khai">
            <a href="{{ route('blog.index') }}" @class(['active' => request()->routeIs('blog.*')]) @if(request()->routeIs('blog.index')) aria-current="page" @endif>Bài viết</a>
            <a href="{{ route('contact.create') }}" @class(['active' => request()->routeIs('contact.*')]) @if(request()->routeIs('contact.*')) aria-current="page" @endif>Liên hệ</a>
        </nav>
        {{-- Tìm bài viết: máy tính hiện sẵn ô tìm; điện thoại bấm biểu tượng kính lúp để mở (details: không cần JS). --}}
        @php($headerQ = request()->routeIs('blog.index') ? mb_substr(trim((string) request()->query('q', '')), 0, 100) : '')
        <form method="GET" action="{{ route('blog.index') }}" class="header-search d-none d-md-flex" role="search" aria-label="Tìm bài viết">
            <label for="hs_q" class="visually-hidden">Tìm bài viết</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" id="hs_q" name="q" value="{{ $headerQ }}" maxlength="100" placeholder="Tìm bài viết…" autocomplete="off" data-submit-on-clear>
        </form>
        <details class="header-search-mobile d-md-none" @if($headerQ !== '') open @endif>
            <summary class="btn btn-sm btn-outline-secondary btn-icon" aria-label="Tìm bài viết" title="Tìm bài viết"><i class="bi bi-search" aria-hidden="true"></i></summary>
            <form method="GET" action="{{ route('blog.index') }}" class="header-search-panel" role="search" aria-label="Tìm bài viết">
                <label for="hs_q_m" class="visually-hidden">Tìm bài viết</label>
                <input type="search" id="hs_q_m" name="q" value="{{ $headerQ }}" maxlength="100" placeholder="Tìm bài viết…" class="form-control" autocomplete="off" data-submit-on-clear>
                <button type="submit" class="btn btn-primary" data-loading-text="Đang tìm…">Tìm</button>
            </form>
        </details>
        {{-- Màn rất hẹp (< 400px): nút chỉ còn biểu tượng, tên đọc qua aria-label/title. --}}
        @guest
            <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary public-header-action" aria-label="Đăng nhập" title="Đăng nhập">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <span class="public-header-action-text" aria-hidden="true">Đăng nhập</span></a>
        @endguest
    </div>
</header>

<main id="main" class="container-xxl public-main" tabindex="-1">
    {{ $slot }}
</main>

<footer class="public-footer d-print-none">
    <div class="container-xxl">
        <p class="public-footer-copy">© {{ now()->year }} BếpMinhBạch. Bảo lưu mọi quyền.</p>
    </div>
</footer>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
{{ $scripts ?? '' }}
</body>
</html>
