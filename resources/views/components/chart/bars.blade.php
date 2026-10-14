{{--
    Biểu đồ thanh ngang, mỗi thanh có nhãn và giá trị ở đầu thanh (đọc được không cần rê chuột).
    - rows: [['label' => '…', 'value' => ?int, 'tone' => 'primary|plan|money|…', 'note' => ?string, 'icon' => ?string]]
      value = null nghĩa là "chưa nhập" (khác 0): không vẽ thanh, ghi rõ chữ.
    - max: đỉnh thang chung (mặc định = giá trị lớn nhất) để nhiều biểu đồ cùng thang khi cần.
--}}
@props(['rows', 'caption', 'format' => 'number', 'max' => null, 'empty' => 'Chưa nhập'])
@php
    use App\Support\Format;
    $fmt = fn (int $v) => $format === 'vnd' ? Format::vnd($v) : Format::number($v);
    $scale = max(1, (int) ($max ?? collect($rows)->max('value')));
@endphp
<figure {{ $attributes->class(['chart', 'mb-0', 'hbar-money' => $format === 'vnd']) }}>
    <ul class="hbar-list" aria-label="{{ $caption }}">
        @foreach($rows as $row)
            @php
                $value = $row['value'];
                $display = $value === null ? $empty : $fmt($value);
                $tip = ['t' => $row['label'], 'r' => [[$row['label'], $display, $row['tone']]]];
                if (! empty($row['note'])) {
                    $tip['n'] = $row['note'];
                }
            @endphp
            <li class="hbar-row" tabindex="0" data-tip="{{ json_encode($tip, JSON_UNESCAPED_UNICODE) }}">
                <span class="hbar-label">
                    @if(! empty($row['icon']))<i class="bi {{ $row['icon'] }}" aria-hidden="true"></i>@endif
                    {{ $row['label'] }}
                </span>
                <span class="hbar-track">
                    @if($value !== null && $value > 0)
                        {{-- Thang tính trên bề ngang trừ chỗ dành cho giá trị (--hbar-reserve): giá trị luôn nằm ngay sau đầu thanh, không tràn --}}
                        <span class="hbar-fill tone-{{ $row['tone'] }}" style="width: calc((100% - var(--hbar-reserve)) * {{ round(min($value, $scale) / $scale, 4) }})"></span>
                    @endif
                    <span @class(['hbar-value', 'is-empty' => $value === null])>{{ $display }}</span>
                </span>
                @if(! empty($row['note']))<span class="visually-hidden">({{ $row['note'] }})</span>@endif
            </li>
        @endforeach
    </ul>
</figure>
