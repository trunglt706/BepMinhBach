@php
    use App\Support\Format;
    $expenseTotal = $batch->expenseItems->sum('amount_vnd');
@endphp
<x-layouts.app :title="'Báo cáo: '.$batch->name">
    <x-page-header :title="'Báo cáo đợt: '.$batch->name" :back="route('reports.index')" back-label="Báo cáo">
        <x-slot:meta>
            <p class="page-subtitle small">Tạo lúc {{ Format::dateTime($generatedAt) }} · Phạm vi: một đợt nấu (mã #{{ $batch->id }})</p>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('reports.batch-export', $batch) }}" class="btn btn-primary" download data-no-loading><i class="bi bi-download" aria-hidden="true"></i> Tải CSV</a>
            <button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer" aria-hidden="true"></i> In</button>
            <a href="{{ route('batches.show', $batch) }}" class="btn btn-outline-secondary">Mở đợt nấu</a>
        </x-slot:actions>
    </x-page-header>

    <div class="callout callout-warning mb-3" role="note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ App\Http\Controllers\ReportController::DISCLAIMER }}</span></div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <section class="card h-100">
                <div class="panel-header"><h2 class="panel-title"><i class="bi bi-info-circle" aria-hidden="true"></i> Thông tin đợt</h2></div>
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
                            <td class="text-end tabular">{{ $batch->expenseItems->isEmpty() ? 'Chưa nhập' : Format::vnd($expenseTotal) }}</td></tr>
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

    <section class="card mb-3">
        <div class="panel-header"><h2 class="panel-title"><i class="bi bi-basket" aria-hidden="true"></i> Nguyên liệu <span class="panel-count">{{ $batch->ingredients->count() }}</span></h2></div>
        @if($batch->ingredients->isEmpty())
            <div class="card-body text-body-secondary">Chưa có nguyên liệu.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th scope="col">Tên</th><th scope="col" class="text-end">Dự kiến</th><th scope="col" class="text-end">Thực tế đã dùng</th><th scope="col">Ghi chú</th></tr></thead>
                    <tbody>
                    @foreach($batch->ingredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->name }}@if($ingredient->needs_restock) <span class="status-pill task-blocked">Đang báo thiếu</span>@endif</td>
                            <td class="text-end text-nowrap tabular">{{ Format::quantity($ingredient->expected_quantity) }} {{ $ingredient->unit }}</td>
                            <td class="text-end text-nowrap tabular">{{ $ingredient->actual_quantity !== null ? Format::quantity($ingredient->actual_quantity).' '.$ingredient->unit : 'Chưa ghi nhận' }}</td>
                            <td class="small">{{ $ingredient->notes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
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
