@props(['icon' => 'bi-inbox', 'title', 'compact' => false])
<div {{ $attributes->class(['empty-state', 'is-compact' => $compact]) }}>
    <div class="empty-state-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
    <p class="empty-state-title">{{ $title }}</p>
    @if(trim($slot) !== '')
        <p class="empty-state-text mb-0">{{ $slot }}</p>
    @endif
    @isset($action)
        <div class="mt-3">{{ $action }}</div>
    @endisset
</div>
