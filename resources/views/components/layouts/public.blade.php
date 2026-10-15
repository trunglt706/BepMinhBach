@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'ogType' => 'website', 'image' => null])
{{--
    Khung trang công khai (khách không cần đăng nhập). Không hiện thông tin nội bộ của tài khoản đang đăng nhập.
    SEO: description, canonical, robots (noindex cho trang tìm kiếm/lọc và bản xem trước), Open Graph + Twitter
    chung cho mọi trang (og:type, title, description, url = canonical, image); slot $head cho thẻ riêng từng trang
    (kích thước ảnh, article:*, dữ liệu có cấu trúc).
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1b6343">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Góp Gió</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif
    <meta name="robots" content="{{ $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large' }}">
    @if($canonical)<link rel="canonical" href="{{ $canonical }}">@endif
    <meta property="og:site_name" content="Góp Gió">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title ?: 'Góp Gió' }}">
    @if($description)<meta property="og:description" content="{{ $description }}">@endif
    @if($canonical)<meta property="og:url" content="{{ $canonical }}">@endif
    @if($image)<meta property="og:image" content="{{ $image }}">@endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title ?: 'Góp Gió' }}">
    @if($description)<meta name="twitter:description" content="{{ $description }}">@endif
    @if($image)<meta name="twitter:image" content="{{ $image }}">@endif
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
            <span class="brand-mark"><i class="bi bi-wind" aria-hidden="true"></i></span>
            <span>Góp <span class="brand-accent">Gió</span></span>
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
    </div>
</header>

<main id="main" class="container-xxl public-main" tabindex="-1">
    {{ $slot }}
</main>

<footer class="public-footer d-print-none">
    <div class="container-xxl">
        <p class="public-footer-copy">© {{ now()->year }} Góp Gió. Bảo lưu mọi quyền.</p>
    </div>
</footer>

{{-- Lên đầu trang: app.js hiện nút khi đã cuộn xuống; không có JS thì nút không xuất hiện. --}}
<button type="button" class="btn btn-primary btn-icon back-to-top d-print-none" data-back-to-top hidden
        aria-label="Lên đầu trang" title="Lên đầu trang">
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
</button>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
{{ $scripts ?? '' }}
</body>
</html>
