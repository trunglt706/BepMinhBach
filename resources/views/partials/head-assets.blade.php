{{--
    CSS + font dùng chung cho mọi layout.
    Preload font icon (bản rút gọn, xem scripts/build-icons.mjs) và font chữ chính để trình duyệt tải ngay
    từ đầu thay vì đợi đọc xong CSS — icon không còn "nhảy" ra muộn.
    URL preload phải trùng từng ký tự với URL trong CSS (kể cả ?v=), nếu không trình duyệt sẽ tải hai lần.
--}}
@php
    $iconFont = 'vendor/bootstrap-icons/fonts/bootstrap-icons-subset.woff2';
    $iconVersion = substr(md5_file(public_path($iconFont)), 0, 10);
@endphp
<link rel="preload" href="{{ asset($iconFont) }}?v={{ $iconVersion }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('vendor/be-vietnam-pro/files/be-vietnam-pro-vietnamese-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('vendor/be-vietnam-pro/files/be-vietnam-pro-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('vendor/be-vietnam-pro/font.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.subset.css') }}?v={{ $iconVersion }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
{{-- Không có JS: ảnh chờ tải (data-src) không bao giờ có src → ẩn đi, dùng bản <noscript> bên cạnh. --}}
<noscript><style>img[data-lazy]{display:none!important}</style></noscript>
