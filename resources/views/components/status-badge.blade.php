@props(['status'])
<span {{ $attributes->class([$status->badgeClass()]) }}>
    <i class="bi {{ $status->icon() }}" aria-hidden="true"></i> {{ $status->label() }}
</span>
