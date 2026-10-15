@php
    use App\Enums\TaskStatus;
    use App\Support\Format;
    $expenseTotal = $batch->expenseItems->sum('amount_vnd');
    $actualCost = $batch->expenseItems->isEmpty() ? null : (int) $expenseTotal;
    $ingredientCost = $batch->ingredientCostVnd();

    // Biểu đồ (cùng quy ước màu với trang chi tiết chương trình: kế hoạch = plan, thực tế = primary, tiền = money)
    $percentOf = fn (?int $part, ?int $whole, string $of) => $part !== null && $whole ? round($part / $whole * 100).'% '.$of : null;
    $servingRows = [
        ['label' => 'Dự kiến', 'value' => $batch->expected_servings, 'tone' => 'plan', 'note' => 'Theo kế hoạch'],
        ['label' => 'Đã chuẩn bị', 'value' => $batch->prepared_servings, 'tone' => 'primary',
            'note' => $percentOf($batch->prepared_servings, $batch->expected_servings, 'so với dự kiến')],
        ['label' => 'Đã phân phát', 'value' => $batch->distributed_servings, 'tone' => 'primary',
            'note' => $percentOf($batch->distributed_servings, $batch->prepared_servings, 'số đã chuẩn bị')],
    ];
    $taskSegments = collect([
        [TaskStatus::Done, 'primary'], [TaskStatus::InProgress, 'info'], [TaskStatus::Todo, 'plan'], [TaskStatus::Blocked, 'critical'],
    ])->map(fn ($s) => [
        'label' => $s[0]->label(), 'icon' => $s[0]->icon(), 'tone' => $s[1],
        'value' => $batch->tasks->where('status', $s[0])->count(),
    ])->all();
    $costRows = [
        ['label' => 'Dự kiến', 'value' => $batch->estimated_cost_vnd, 'tone' => 'plan'],
        ['label' => 'Thực tế (khoản chi)', 'value' => $actualCost, 'tone' => 'money',
            'note' => $percentOf($actualCost, $batch->estimated_cost_vnd, 'so với dự kiến')],
        ['label' => 'Tiền nguyên liệu (theo đơn giá)', 'value' => $ingredientCost, 'tone' => 'plan', 'note' => 'Ước tính để tham khảo'],
    ];
    // Chi phí trên mỗi suất đã phân phát (chỉ khi có đủ hai số)
    $costPerServing = $actualCost !== null && $batch->distributed_servings ? (int) round($actualCost / $batch->distributed_servings) : null;
    $expenseRows = $batch->expenseItems->sortByDesc('amount_vnd')->take(5)
        ->map(fn ($e) => ['label' => $e->name, 'value' => (int) $e->amount_vnd, 'tone' => 'money'])->values()->all();
    $ingredientRows = $batch->ingredients->filter(fn ($i) => $i->costVnd() !== null)->sortByDesc(fn ($i) => $i->costVnd())->take(6)
        ->map(fn ($i) => ['label' => $i->name, 'value' => $i->costVnd(), 'tone' => 'money',
            'note' => Format::quantity($i->actual_quantity ?? $i->expected_quantity).' '.$i->unit.' × '.Format::vnd($i->unit_price_vnd)])->values()->all();
@endphp
<x-layouts.app :title="'Báo cáo: '.$batch->name">
    <x-page-header :title="'Báo cáo chương trình: '.$batch->name" :back="route('reports.index')" back-label="Báo cáo">
        <x-slot:meta>
            <p class="page-subtitle small">Tạo lúc {{ Format::dateTime($generatedAt) }} · Phạm vi: một chương trình (mã #{{ $batch->id }})</p>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('reports.batch-export', $batch) }}" class="btn btn-primary" download data-no-loading><i class="bi bi-download" aria-hidden="true"></i> Tải CSV</a>
            <button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer" aria-hidden="true"></i> In</button>
            <a href="{{ route('batches.show', $batch) }}" class="btn btn-outline-secondary">Mở chương trình</a>
        </x-slot:actions>
    </x-page-header>

    <div class="callout callout-warning mb-3" role="note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ App\Http\Controllers\ReportController::DISCLAIMER }}</span></div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <section class="card h-100">
                <div class="panel-header"><h2 class="panel-title"><i class="bi bi-info-circle" aria-hidden="true"></i> Thông tin chương trình</h2></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-body-secondary fw-semibold">Thời gian</dt><dd class="col-7">{{ Format::date($batch->starts_on) }}</dd>
                        <dt class="col-5 text-body-secondary fw-semibold">Địa điểm</dt><dd class="col-7">{{ $batch->location_text ?: '—' }}</dd>
                        <dt class="col-5 text-body-secondary fw-semibold">Trạng thái</dt><dd class="col-7"><x-status-badge :status="$batch->status" /></dd>
                        <dt class="col-5 text-body-secondary fw-semibold">Điều phối</dt><dd class="col-7">{{ $batch->coordinator?->name }}</dd>
                        <dt class="col-5 text-body-secondary fw-semibold">Công việc</dt><dd class="col-7 mb-0">{{ $batch->tasks_done_count }}/{{ $batch->tasks->count() }} đã xong</dd>
                    </dl>
                </div>
            </section>
        </div>
        <div class="col-lg-7">
            <section class="card h-100">
                <div class="panel-header"><h2 class="panel-title"><i class="bi bi-box2-heart" aria-hidden="true"></i> Số suất và chi phí</h2></div>
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th scope="col"><span class="visually-hidden">Hạng mục</span></th><th scope="col" class="text-end">Dự kiến</th><th scope="col" class="text-end">Thực tế</th></tr></thead>
                        <tbody>
                        <tr><th scope="row">Suất chuẩn bị</th><td class="text-end tabular">{{ Format::number($batch->expected_servings) }}</td><td class="text-end tabular">{{ Format::number($batch->prepared_servings) }}</td></tr>
                        <tr><th scope="row">Suất phân phát</th><td class="text-end">—</td><td class="text-end tabular">{{ Format::number($batch->distributed_servings) }}</td></tr>
                        <tr><th scope="row">Chi phí</th><td class="text-end tabular">{{ Format::vnd($batch->estimated_cost_vnd) }}</td>
                            <td class="text-end tabular">{{ $batch->expenseItems->isEmpty() ? Format::EMPTY : Format::vnd($expenseTotal) }}</td></tr>
                        <tr><th scope="row">Tiền nguyên liệu <span class="fw-normal small text-body-secondary">(theo đơn giá)</span></th><td class="text-end">—</td>
                            <td class="text-end tabular">{{ Format::vnd($ingredientCost) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                @if($batch->servingVariances() || $batch->variance_note)
                    <div class="card-body pt-2">
                        @foreach($batch->servingVariances() as $variance)
                            <div class="callout callout-{{ $variance['level'] }} mb-2"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ $variance['message'] }}</span></div>
                        @endforeach
                        @if($batch->variance_note)<div class="small"><strong>Giải thích:</strong> {{ $batch->variance_note }}</div>@endif
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Biểu đồ: số suất, công việc, chi phí, cơ cấu chi. "—" = chưa nhập (khác 0). --}}
    <section aria-labelledby="h-charts" class="mb-3">
        <h2 id="h-charts" class="visually-hidden">Biểu đồ</h2>
        <div class="row g-3">
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="panel-header"><h3 class="panel-title"><i class="bi bi-box2-heart" aria-hidden="true"></i> Số suất</h3></div>
                    <div class="card-body"><x-chart.bars :rows="$servingRows" caption="Số suất dự kiến, đã chuẩn bị và đã phân phát" /></div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="panel-header"><h3 class="panel-title"><i class="bi bi-list-check" aria-hidden="true"></i> Công việc theo trạng thái</h3></div>
                    <div class="card-body">
                        @if($batch->tasks->isEmpty())
                            <p class="small text-body-secondary mb-0">Chưa có công việc.</p>
                        @else
                            <x-chart.stack unit="việc" caption="Công việc theo trạng thái" :segments="$taskSegments" />
                        @endif
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="panel-header"><h3 class="panel-title"><i class="bi bi-cash-coin" aria-hidden="true"></i> Chi phí</h3></div>
                    <div class="card-body">
                        <x-chart.bars :rows="$costRows" format="vnd" caption="Chi phí dự kiến, thực tế và tiền nguyên liệu" />
                        <p class="small mb-0 mt-3">Chi phí mỗi suất đã phân phát:
                            <strong class="tabular">{{ $costPerServing !== null ? Format::vnd($costPerServing) : Format::EMPTY }}</strong>
                            <span class="text-body-secondary">(chi phí thực tế ÷ số suất đã phân phát)</span></p>
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card h-100">
                    <div class="panel-header"><h3 class="panel-title"><i class="bi bi-pie-chart" aria-hidden="true"></i> Cơ cấu chi</h3></div>
                    <div class="card-body">
                        <h4 class="small fw-bold text-body-secondary mb-2">Khoản chi lớn nhất</h4>
                        @if($expenseRows)
                            <x-chart.bars :rows="$expenseRows" format="vnd" caption="5 khoản chi lớn nhất" class="mb-3" />
                        @else
                            <p class="small text-body-secondary">Chưa nhập khoản chi nào.</p>
                        @endif
                        <h4 class="small fw-bold text-body-secondary mb-2">Tiền nguyên liệu theo đơn giá</h4>
                        @if($ingredientRows)
                            <x-chart.bars :rows="$ingredientRows" format="vnd" caption="Nguyên liệu tốn tiền nhất (theo đơn giá đã nhập)" />
                        @else
                            <p class="small text-body-secondary mb-0">Chưa có nguyên liệu nào nhập đơn giá.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </section>

    <section class="card mb-3">
        <div class="panel-header"><h2 class="panel-title"><i class="bi bi-basket" aria-hidden="true"></i> Nguyên liệu <span class="panel-count">{{ $batch->ingredients->count() }}</span></h2></div>
        @if($batch->ingredients->isEmpty())
            <div class="card-body text-body-secondary">Chưa có nguyên liệu.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th scope="col">Tên</th><th scope="col" class="text-end">Dự kiến</th><th scope="col" class="text-end">Thực tế đã dùng</th><th scope="col" class="text-end">Đơn giá</th><th scope="col" class="text-end">Thành tiền</th><th scope="col">Ghi chú</th></tr></thead>
                    <tbody>
                    @foreach($batch->ingredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->name }}@if($ingredient->needs_restock) <span class="status-pill task-blocked">Đang báo thiếu</span>@endif</td>
                            <td class="text-end text-nowrap tabular">{{ Format::quantity($ingredient->expected_quantity) }} {{ $ingredient->unit }}</td>
                            <td class="text-end text-nowrap tabular">{{ $ingredient->actual_quantity !== null ? Format::quantity($ingredient->actual_quantity).' '.$ingredient->unit : Format::EMPTY }}</td>
                            <td class="text-end text-nowrap tabular">{{ $ingredient->unit_price_vnd !== null ? Format::vnd($ingredient->unit_price_vnd).'/'.$ingredient->unit : Format::EMPTY }}</td>
                            <td class="text-end text-nowrap tabular">{{ Format::vnd($ingredient->costVnd()) }}</td>
                            <td class="small">{{ $ingredient->notes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr>
                        <th scope="row" colspan="4">Tổng tiền nguyên liệu <span class="fw-normal small text-body-secondary">(ước tính theo đơn giá đã nhập)</span></th>
                        <th class="text-end text-nowrap tabular">{{ Format::vnd($ingredientCost) }}</th><td></td>
                    </tr></tfoot>
                </table>
            </div>
        @endif
    </section>

    <section class="card mb-3">
        <div class="panel-header"><h2 class="panel-title"><i class="bi bi-list-check" aria-hidden="true"></i> Công việc <span class="panel-count">{{ $batch->tasks->count() }}</span></h2></div>
        @if($batch->tasks->isEmpty())
            <div class="card-body text-body-secondary">Chưa có công việc.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th scope="col">Việc</th><th scope="col">Phụ trách</th><th scope="col">Hạn</th><th scope="col">Trạng thái</th></tr></thead>
                    <tbody>
                    @foreach($batch->tasks as $task)
                        <tr>
                            <td>{{ $task->title }}@if($task->blocked_note && $task->status === App\Enums\TaskStatus::Blocked)<div class="small text-danger">{{ $task->blocked_note }}</div>@endif</td>
                            <td>{{ $task->assignee?->name ?? '—' }}</td>
                            <td class="text-nowrap">{{ Format::dateTime($task->due_at) }}</td>
                            <td><x-task-status :status="$task->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card mb-3">
        <div class="panel-header"><h2 class="panel-title"><i class="bi bi-cash-coin" aria-hidden="true"></i> Khoản chi <span class="panel-count">{{ $batch->expenseItems->count() }}</span></h2></div>
        @if($batch->expenseItems->isEmpty())
            <div class="card-body text-body-secondary">Chưa nhập khoản chi nào.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th scope="col">Khoản chi</th><th scope="col" class="text-end">Số tiền</th><th scope="col">Ghi chú</th></tr></thead>
                    <tbody>
                    @foreach($batch->expenseItems as $expense)
                        <tr><td>{{ $expense->name }}</td><td class="text-end text-nowrap tabular">{{ Format::vnd($expense->amount_vnd) }}</td><td class="small">{{ $expense->note }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><th scope="row">Tổng</th><th class="text-end text-nowrap tabular">{{ Format::vnd($expenseTotal) }}</th><td></td></tr></tfoot>
                </table>
            </div>
        @endif
    </section>

    @if($batch->completion_notes)
        <section class="card">
            <div class="panel-header"><h2 class="panel-title"><i class="bi bi-journal-text" aria-hidden="true"></i> Ghi chú rút kinh nghiệm</h2></div>
            <div class="card-body text-pre-line">{{ $batch->completion_notes }}</div>
        </section>
    @endif
</x-layouts.app>
