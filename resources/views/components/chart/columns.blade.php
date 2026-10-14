{{--
    Biểu đồ cột theo thời gian (1–3 chuỗi cạnh nhau), render phía server.
    - groups: [['label' => 'd/m', 'title' => 'Tuần …', 'values' => ['key' => int]]]
    - series: ['key' => ['label' => '…', 'tone' => 'primary|plan|money']]
    Rê chuột/chạm/focus vào một nhóm để xem giá trị (app.js, data-tip); bảng số liệu luôn có trong "Xem dạng bảng".
--}}
@props(['groups', 'series', 'caption', 'format' => 'number', 'height' => 180])
@php
    use App\Support\Charts;
    use App\Support\Format;
    $fmt = fn (int $v) => $format === 'vnd' ? Format::vnd($v) : Format::number($v);
    $all = collect($groups)->flatMap(fn ($g) => array_values($g['values']));
    $max = Charts::niceMax((int) $all->max());
    $dense = count($groups) > 8;
@endphp
<figure {{ $attributes->class(['chart', 'mb-0']) }}>
    @if(count($series) > 1)
        <ul class="chart-legend" aria-label="Chú giải">
            @foreach($series as $s)
                <li><span class="chart-swatch tone-{{ $s['tone'] }}" aria-hidden="true"></span>{{ $s['label'] }}</li>
            @endforeach
        </ul>
    @endif

    <div class="col-chart" style="--chart-h: {{ $height }}px">
        <div class="col-yaxis" aria-hidden="true">
            @foreach([1, 0.5, 0] as $f)
                <span style="bottom: {{ $f * 100 }}%">{{ Charts::compact((int) round($max * $f)) }}</span>
            @endforeach
        </div>
        <div class="col-plot">
            <div class="col-grid" aria-hidden="true"><span></span><span></span><span></span></div>
            <div @class(['col-groups', 'is-dense' => $dense])>
                @foreach($groups as $i => $g)
                    @php
                        $rows = collect($series)->map(fn ($s, $key) => [$s['label'], $fmt($g['values'][$key]), $s['tone']])->values()->all();
                        $spoken = $g['title'].': '.collect($rows)->map(fn ($r) => $r[0].' '.$r[1])->implode('; ');
                    @endphp
                    <div class="col-group" role="img" tabindex="0" aria-label="{{ $spoken }}"
                         data-tip="{{ json_encode(['t' => $g['title'], 'r' => $rows], JSON_UNESCAPED_UNICODE) }}">
                        <div class="col-bars">
                            @foreach($series as $key => $s)
                                @php($v = $g['values'][$key])
                                <span class="col-bar tone-{{ $s['tone'] }} @if($v > 0) has-value @endif" style="height: {{ round($v / $max * 100, 2) }}%"></span>
                            @endforeach
                        </div>
                        <span @class(['col-x', 'is-minor' => $dense && $i % 2 === 1])>{{ $g['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <details class="chart-table d-print-none">
        <summary>Xem dạng bảng</summary>
        <div class="table-scroll">
            <table class="table table-sm mb-0">
                <caption class="visually-hidden">{{ $caption }}</caption>
                <thead><tr><th scope="col">Thời gian</th>@foreach($series as $s)<th scope="col" class="text-end">{{ $s['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($groups as $g)
                    <tr><th scope="row" class="fw-normal">{{ $g['title'] }}</th>@foreach($series as $key => $s)<td class="text-end tabular">{{ $fmt($g['values'][$key]) }}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
