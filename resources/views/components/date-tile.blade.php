@props(['date', 'size' => null])
@php
    $weekdays = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
@endphp
<div {{ $attributes->class(['date-tile', 'date-tile-lg' => $size === 'lg', 'is-today' => $date->isToday()]) }}
     aria-label="{{ $date->isToday() ? 'Hôm nay, ' : '' }}ngày {{ $date->format('d/m/Y') }}" role="img">
    <span class="date-tile-month">{{ $date->isToday() ? 'Hôm nay' : 'Th'.$date->format('n') }}</span>
    <span class="date-tile-day">{{ $date->format('d') }}</span>
    <span class="date-tile-weekday">{{ $weekdays[$date->dayOfWeek] }}</span>
</div>
