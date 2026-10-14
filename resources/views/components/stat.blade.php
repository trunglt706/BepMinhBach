@props(['label', 'value' => null, 'icon' => 'bi-circle', 'tone' => 'green', 'hint' => null, 'href' => null, 'empty' => false, 'compact' => false])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['stat']) }}>
    <span class="stat-head">
        <span class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
        {{ $label }}
    </span>
    <span @class(['stat-value', 'is-empty' => $empty, 'is-compact' => $compact])>{{ $value ?? $slot }}</span>
    @if($hint)<span class="stat-hint">{{ $hint }}</span>@endif
</{{ $tag }}>
