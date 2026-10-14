<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · BếpMinhBạch</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('vendor/be-vietnam-pro/font.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<main class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card card-body text-center p-4 p-md-5" style="max-width: 520px;">
        <a class="brand justify-content-center mb-4" href="{{ url('/') }}">
            <span class="brand-mark"><i class="bi bi-egg-fried" aria-hidden="true"></i></span>
            <span>Bếp<span class="brand-accent">MinhBạch</span></span>
        </a>
        <p class="text-body-secondary fw-semibold mb-1">Mã lỗi @yield('code')</p>
        <h1 class="h3">@yield('title')</h1>
        <p class="text-body-secondary">@yield('message')</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
            <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Quay lại</a>
            <a href="{{ url('/') }}" class="btn btn-primary">Về trang tổng quan</a>
        </div>
    </div>
</main>
</body>
</html>
