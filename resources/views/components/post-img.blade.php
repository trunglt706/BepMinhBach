@props(['image', 'width' => 480, 'sizes' => null, 'alt' => '', 'priority' => false])
{{--
    Ảnh bài viết (WebP nhiều kích thước).
    - priority: ảnh lớn đầu trang (LCP) → tải ngay, ưu tiên cao.
    - còn lại: chỉ tải khi ảnh đi vào khung nhìn (app.js, IntersectionObserver gán data-src/data-srcset).
      Không có JS (và cho máy tìm kiếm không chạy JS): bản <noscript> có src thật + loading="lazy";
      ảnh chờ được ẩn bằng style trong <noscript> ở partials/head-assets.
    width/height luôn khai báo để giữ chỗ, trang không bị giật khi ảnh tải xong.
--}}
@php($height = $image->heightFor($width))
@if($priority)
    <img src="{{ $image->url($width) }}" srcset="{{ $image->srcset() }}" @if($sizes) sizes="{{ $sizes }}" @endif
         width="{{ $width }}" height="{{ $height }}" alt="{{ $alt }}" fetchpriority="high" decoding="async" {{ $attributes }}>
@else
    <img data-lazy data-src="{{ $image->url($width) }}" data-srcset="{{ $image->srcset() }}" @if($sizes) sizes="{{ $sizes }}" @endif
         width="{{ $width }}" height="{{ $height }}" alt="{{ $alt }}" decoding="async" {{ $attributes }}>
    <noscript><img src="{{ $image->url($width) }}" srcset="{{ $image->srcset() }}" @if($sizes) sizes="{{ $sizes }}" @endif
         width="{{ $width }}" height="{{ $height }}" alt="{{ $alt }}" loading="lazy" decoding="async" {{ $attributes }}></noscript>
@endif
