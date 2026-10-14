{{--
    Thanh xếp chồng 100% (một phần của tổng thể), ví dụ công việc theo trạng thái.
    - segments: [['label' => '…', 'value' => int, 'tone' => '…', 'icon' => 'bi-…']]
    Chú giải bên dưới luôn ghi biểu tượng + tên + số lượng: không phụ thuộc vào màu.
--}}
@props(['segments', 'caption', 'unit' => ''])
@php
    $total = max(1, collect($segments)->sum('value'));
    $pct = fn (int $v) => (int) round($v / $total * 100);
@endphp
<figure {{ $attributes->class(['chart', 'mb-0']) }}>
    <div class="stack-bar" role="group" aria-label="{{ $caption }}">
        @foreach($segments as $s)
            @continue($s['value'] <= 0)
            <span class="stack-seg tone-{{ $s['tone'] }}" style="flex-grow: {{ $s['value'] }}" tabindex="0" role="img"
                  aria-label="{{ $s['label'] }}: {{ $s['value'] }} {{ $unit }} ({{ $pct($s['value']) }}%)"
                  data-tip="{{ json_encode(['t' => $s['label'], 'r' => [[$s['label'], $s['value'].' '.$unit.' · '.$pct($s['value']).'%', $s['tone']]]], JSON_UNESCAPED_UNICODE) }}"></span>
        @endforeach
    </div>
    <ul class="stack-legend">
        @foreach($segments as $s)
            <li @class(['is-zero' => $s['value'] <= 0])>
                <span class="chart-swatch tone-{{ $s['tone'] }}" aria-hidden="true"></span>
                <i class="bi {{ $s['icon'] }}" aria-hidden="true"></i>
                <span>{{ $s['label'] }}</span>
                <strong class="tabular">{{ $s['value'] }}</strong>
            </li>
        @endforeach
    </ul>
</figure>
