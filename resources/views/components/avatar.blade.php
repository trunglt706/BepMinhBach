@props(['name' => '', 'size' => null])
@php
    $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    $initials = $words
        ? mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : '')
        : '?';
    $tone = 'avatar-tone-'.(crc32((string) $name) % 5 + 1);
@endphp
<span {{ $attributes->class(['avatar', $tone, 'avatar-sm' => $size === 'sm', 'avatar-lg' => $size === 'lg']) }} aria-hidden="true">{{ $initials }}</span>
