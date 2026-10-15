@props(['sponsor', 'size' => 'md'])
{{-- Logo đơn vị (giữ nguyên tỉ lệ, không cắt) hoặc chữ viết tắt khi chưa có logo. size: sm | md | lg --}}
@php($logo = $sponsor->logo())
<span {{ $attributes->class(['sponsor-logo', 'sponsor-logo-'.$size, 'is-empty' => ! $logo]) }}>
    @if($logo)
        <img src="{{ $logo->url(480) }}" srcset="{{ $logo->srcset() }}" sizes="160px" alt="Logo {{ $sponsor->name }}"
             width="{{ $logo->width }}" height="{{ $logo->height }}" loading="lazy" decoding="async">
    @else
        <span aria-hidden="true">{{ $sponsor->initials() }}</span>
    @endif
</span>
