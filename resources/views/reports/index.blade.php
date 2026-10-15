@php use App\Support\Format; @endphp
<x-layouts.app title="Báo cáo">
    <x-page-header title="Báo cáo chương trình" subtitle="Tổng hợp số suất và chi phí theo khoảng thời gian.">
        <x-slot:actions>
            <a href="{{ route('reports.export', request()->only(['q', 'status', 'from', 'to'])) }}" class="btn btn-primary" download data-no-loading>
                <i class="bi bi-download" aria-hidden="true"></i> Tải CSV
            </a>
            <button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer" aria-hidden="true"></i> In</button>
            <x-filter-toggle target="report-filter" :active="! $filter->isEmpty()" />
        </x-slot:actions>
    </x-page-header>

    <div id="report-filter" class="d-print-none" data-mobile-filter>
        @include('partials.batch-filter', ['action' => route('reports.index'), 'submitLabel' => 'Xem báo cáo', 'statusMode' => 'select'])
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
        <p class="small text-body-secondary mb-0">
            <strong>Phạm vi:</strong> {{ $filter->describe() }} · <strong>Tạo lúc:</strong> {{ Format::dateTime($generatedAt) }}
        </p>
    </div>
    <div class="callout callout-warning mb-3" role="note">
        <i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ App\Http\Controllers\ReportController::DISCLAIMER }}</span>
    </div>

    @if($summary['count'] === 0)
        <div class="card">
            <x-empty-state icon="bi-bar-chart" title="Không có chương trình nào trong phạm vi này">
                Hãy đổi khoảng ngày hoặc trạng thái.
            </x-empty-state>
        </div>
    @else
        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-lg">
                <x-stat label="Số chương trình" icon="bi-calendar3" tone="gray" :value="$summary['count']"
                        :hint="$summary['cancelled'] ? 'gồm '.$summary['cancelled'].' chương trình đã hủy' : 'Trong phạm vi lọc'" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Suất dự kiến" icon="bi-clipboard" tone="blue" :value="Format::number($summary['expected'])" hint="Kế hoạch" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Suất đã chuẩn bị" icon="bi-fire" tone="amber" :value="$summary['preparedCount'] ? Format::number($summary['prepared']) : '—'"
                        :empty="! $summary['preparedCount']" :hint="'Thực tế · '.$summary['preparedCount'].'/'.$summary['count'].' chương trình đã nhập'" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Suất đã phân phát" icon="bi-box2-heart" tone="green" :value="$summary['distributedCount'] ? Format::number($summary['distributed']) : '—'"
                        :empty="! $summary['distributedCount']" :hint="'Thực tế · '.$summary['distributedCount'].'/'.$summary['count'].' chương trình đã nhập'" />
            </div>
            <div class="col-12 col-lg">
                <x-stat label="Chi phí thực tế" icon="bi-cash-coin" tone="amber" compact :value="$summary['expenseBatchCount'] ? Format::vnd($summary['expenseTotal']) : '—'"
                        :empty="! $summary['expenseBatchCount']" :hint="$summary['expenseBatchCount'].'/'.$summary['count'].' chương trình đã nhập chi phí'" />
            </div>
        </div>

        <section class="card" aria-labelledby="list-title">
            <div class="panel-header">
                <h2 id="list-title" class="panel-title"><i class="bi bi-table" aria-hidden="true"></i> Chi tiết theo chương trình</h2>
                <span class="small text-body-secondary">“—” nghĩa là chưa nhập (khác với 0)</span>
            </div>

            {{-- Bảng (máy tính bảng/desktop, và khi in) --}}
            <div class="table-scroll d-none d-md-block d-print-block">
                <table class="table table-hover align-middle" data-skeleton="table">
                    <caption class="visually-hidden">Danh sách chương trình trong phạm vi báo cáo</caption>
                    <thead>
                    <tr>
                        <th scope="col">Chương trình</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col" class="text-end">Dự kiến</th>
                        <th scope="col" class="text-end">Chuẩn bị</th>
                        <th scope="col" class="text-end">Phân phát</th>
                        <th scope="col" class="text-end">Chi phí</th>
                        <th scope="col">Công việc</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($batches as $batch)
                        <tr>
                            <td>
                                <a href="{{ route('reports.show', $batch) }}" class="fw-semibold">{{ $batch->name }}</a>
                                <div class="small text-body-secondary">{{ Format::date($batch->starts_on) }}</div>
                            </td>
                            <td><x-status-badge :status="$batch->status" /></td>
                            <td class="text-end tabular">{{ Format::number($batch->expected_servings) }}</td>
                            <td class="text-end tabular">{{ Format::number($batch->prepared_servings, '—') }}</td>
                            <td class="text-end tabular">{{ Format::number($batch->distributed_servings, '—') }}</td>
                            <td class="text-end text-nowrap tabular">{{ $batch->expense_items_count ? Format::vnd((int) $batch->expense_items_sum_amount_vnd) : '—' }}</td>
                            <td class="small text-nowrap">{{ $batch->tasks_done_count }}/{{ $batch->tasks_count }} xong</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Thẻ (điện thoại) --}}
            <div class="d-md-none d-print-none" data-skeleton="list" data-skeleton-leading="tile">
                @foreach($batches as $batch)
                    <a href="{{ route('reports.show', $batch) }}" class="batch-row align-items-start">
                        <x-date-tile :date="$batch->starts_on" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold">{{ $batch->name }}</div>
                            <x-status-badge :status="$batch->status" class="my-1" />
                            <dl class="row g-0 small mb-0 mt-1">
                                <dt class="col-6 fw-normal text-body-secondary">Dự kiến</dt><dd class="col-6 text-end mb-0 tabular">{{ Format::number($batch->expected_servings) }}</dd>
                                <dt class="col-6 fw-normal text-body-secondary">Chuẩn bị / Phân phát</dt>
                                <dd class="col-6 text-end mb-0 tabular">{{ Format::number($batch->prepared_servings, '—') }} / {{ Format::number($batch->distributed_servings, '—') }}</dd>
                                <dt class="col-6 fw-normal text-body-secondary">Chi phí</dt>
                                <dd class="col-6 text-end mb-0 tabular">{{ $batch->expense_items_count ? Format::vnd((int) $batch->expense_items_sum_amount_vnd) : '—' }}</dd>
                            </dl>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
        <div class="mt-4 d-print-none">{{ $batches->links() }}</div>
    @endif
</x-layouts.app>
