@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => null])
{{-- Tiêu đề trang thống nhất: liên kết quay lại (thay breadcrumb trên điện thoại), tiêu đề, mô tả, hành động. --}}
<header {{ $attributes->class(['page-header']) }}>
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="back-link d-print-none"><i class="bi bi-chevron-left" aria-hidden="true"></i> {{ $backLabel ?? 'Quay lại' }}</a>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="page-actions d-print-none">{{ $actions }}</div>
    @endisset
</header>
