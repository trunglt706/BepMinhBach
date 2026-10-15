@php
    use App\Enums\BatchStatus;
    use App\Enums\TaskStatus;
    use App\Support\Format;
    $user = auth()->user();
    $taskTotal = $batch->tasks->count();
    $doneCount = $batch->tasks->where('status', TaskStatus::Done)->count();
    $blockedCount = $batch->tasks->where('status', TaskStatus::Blocked)->count();
    $shortageCount = $batch->ingredients->where('needs_restock', true)->count();
    $percent = $taskTotal > 0 ? (int) round($doneCount / $taskTotal * 100) : 0;
    $expenseTotal = $canViewFinancials ? $batch->expenseItems->sum('amount_vnd') : null;

    // Biểu đồ: màu trạng thái có nghĩa cố định (xong = xanh lá, vướng = đỏ), luôn kèm biểu tượng + tên.
    $taskSegments = collect([
        [TaskStatus::Done, 'primary'], [TaskStatus::InProgress, 'info'], [TaskStatus::Todo, 'plan'], [TaskStatus::Blocked, 'critical'],
    ])->map(fn ($s) => [
        'label' => $s[0]->label(), 'icon' => $s[0]->icon(), 'tone' => $s[1],
        'value' => $batch->tasks->where('status', $s[0])->count(),
    ])->all();
    $percentOf = fn (?int $part, ?int $whole, string $of) => $part !== null && $whole ? round($part / $whole * 100).'% '.$of : null;
    $servingRows = [
        ['label' => 'Dự kiến', 'value' => $batch->expected_servings, 'tone' => 'plan', 'note' => 'Theo kế hoạch'],
        ['label' => 'Đã chuẩn bị', 'value' => $batch->prepared_servings, 'tone' => 'primary',
            'note' => $percentOf($batch->prepared_servings, $batch->expected_servings, 'so với dự kiến')],
        ['label' => 'Đã phân phát', 'value' => $batch->distributed_servings, 'tone' => 'primary',
            'note' => $percentOf($batch->distributed_servings, $batch->prepared_servings, 'số đã chuẩn bị')],
    ];
    if ($canViewFinancials) {
        $actualCost = $batch->expenseItems->isEmpty() ? null : (int) $expenseTotal;
        $costRows = [
            ['label' => 'Dự kiến', 'value' => $batch->estimated_cost_vnd, 'tone' => 'plan'],
            ['label' => 'Thực tế đã nhập', 'value' => $actualCost, 'tone' => 'money',
                'note' => $percentOf($actualCost, $batch->estimated_cost_vnd, 'so với dự kiến')],
        ];
        // Theo khoản chi: 5 khoản lớn nhất, phần còn lại gộp vào "Khác" (không thêm màu mới).
        $sorted = $batch->expenseItems->sortByDesc('amount_vnd')->values();
        $expenseRows = $sorted->take(5)->map(fn ($e) => ['label' => $e->name, 'value' => (int) $e->amount_vnd, 'tone' => 'money'])->all();
        if ($sorted->count() > 5) {
            $rest = $sorted->slice(5);
            $expenseRows[] = ['label' => 'Khác ('.$rest->count().' khoản)', 'value' => (int) $rest->sum('amount_vnd'), 'tone' => 'plan'];
        }
    }

    // Một hành động chính duy nhất = bước tiếp theo của quy trình.
    $primary = null;
    if ($user->can('changeStatus', $batch) && $batch->status === BatchStatus::Draft) {
        $primary = ['type' => 'status', 'to' => BatchStatus::Planned->value, 'label' => 'Chốt kế hoạch', 'icon' => 'bi-calendar-check'];
    } elseif ($user->can('changeStatus', $batch) && $batch->status === BatchStatus::Planned) {
        $primary = ['type' => 'status', 'to' => BatchStatus::InProgress->value, 'label' => 'Bắt đầu thực hiện', 'icon' => 'bi-fire'];
    } elseif ($batch->status === BatchStatus::InProgress && $user->can('complete', $batch)) {
        $primary = ['type' => 'link', 'url' => route('batches.complete', $batch), 'label' => 'Hoàn tất chương trình', 'icon' => 'bi-check-circle'];
    } elseif ($user->can('reopen', $batch)) {
        $primary = ['type' => 'link', 'url' => route('batches.reopen', $batch), 'label' => 'Mở lại chương trình', 'icon' => 'bi-unlock', 'class' => 'btn-outline-warning'];
    }
    $canEdit = $user->can('update', $batch);
    $canToDraft = $batch->status === BatchStatus::Planned && $user->can('changeStatus', $batch);
    $canCompleteEarly = $batch->status === BatchStatus::Planned && $user->can('complete', $batch);
    $canCancel = $user->can('cancel', $batch);
    $canReport = $user->can('viewReports');
    $canDelete = $user->can('delete', $batch);
    $hasMenu = $canEdit || $canDelete || $canToDraft || $canCompleteEarly || $canCancel || $canReport;
@endphp
<x-layouts.app :title="$batch->name">
<div id="batch-page" data-region>
    <a href="{{ route('batches.index') }}" class="back-link d-print-none"><i class="bi bi-chevron-left" aria-hidden="true"></i> Chương trình</a>

    {{-- Phần đầu trang --}}
    <section class="card mb-3" aria-labelledby="batch-title">
        @if($cover = $batch->coverImage())
            {{-- Ảnh nền của chương trình (cũng dùng cho slider trang chủ và trang chương trình công khai). --}}
            <div class="batch-cover-banner">
                <x-post-img :image="$cover" :width="960" priority sizes="(min-width: 1400px) 1100px, 100vw" alt="" />
            </div>
        @endif
        <div class="batch-hero">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-start">
                <div class="d-flex gap-3 flex-grow-1 min-w-0">
                    <x-date-tile :date="$batch->starts_on" size="lg" />
                    <div class="min-w-0">
                        <x-status-badge :status="$batch->status" class="mb-2" />
                        <h1 id="batch-title" class="batch-hero-title">{{ $batch->name }}</h1>
                        <div class="meta-inline">
                            <span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ Format::date($batch->starts_on) }}</span>
                            @if($batch->location_text)<span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $batch->location_text }}</span>@endif
                            <span><i class="bi bi-person-badge" aria-hidden="true"></i> Điều phối: {{ $batch->coordinator?->name }}</span>
                        </div>
                    </div>
                </div>

                <div class="page-actions d-print-none flex-md-nowrap">
                    @if($primary)
                        @if($primary['type'] === 'status')
                            <form method="POST" action="{{ route('batches.status', $batch) }}" data-ajax>
                                @csrf
                                <input type="hidden" name="to" value="{{ $primary['to'] }}">
                                <button type="submit" class="btn btn-primary"><i class="bi {{ $primary['icon'] }}" aria-hidden="true"></i> {{ $primary['label'] }}</button>
                            </form>
                        @else
                            <a href="{{ $primary['url'] }}" data-modal class="btn {{ $primary['class'] ?? 'btn-primary' }}"><i class="bi {{ $primary['icon'] }}" aria-hidden="true"></i> {{ $primary['label'] }}</a>
                        @endif
                    @endif
                    <a href="{{ route('batches.print', $batch) }}" class="btn btn-outline-secondary" style="flex-grow: 0" aria-label="In checklist">
                        <i class="bi bi-printer" aria-hidden="true"></i> <span @class(['d-none d-sm-inline' => $primary]) aria-hidden="true">In checklist</span>
                    </a>
                    @if($hasMenu)
                        <div class="dropdown" style="flex-grow: 0">
                            <button type="button" class="btn btn-outline-secondary btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Thao tác khác với chương trình">
                                <i class="bi bi-three-dots" aria-hidden="true"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @if($canEdit)
                                    <li><a class="dropdown-item" href="{{ route('batches.edit', $batch) }}" data-modal><i class="bi bi-pencil" aria-hidden="true"></i> Sửa thông tin</a></li>
                                @endif
                                @if($canCompleteEarly)
                                    <li><a class="dropdown-item" href="{{ route('batches.complete', $batch) }}" data-modal><i class="bi bi-check-circle" aria-hidden="true"></i> Hoàn tất chương trình</a></li>
                                @endif
                                @if($canToDraft)
                                    <li>
                                        <form method="POST" action="{{ route('batches.status', $batch) }}" data-ajax>
                                            @csrf
                                            <input type="hidden" name="to" value="{{ BatchStatus::Draft->value }}">
                                            <button type="submit" class="dropdown-item"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Chuyển về nháp</button>
                                        </form>
                                    </li>
                                @endif
                                @can('create', App\Models\Post::class)
                                    <li><a class="dropdown-item" href="{{ route('posts.create', ['batch' => $batch->id]) }}" data-modal><i class="bi bi-pencil-square" aria-hidden="true"></i> Viết bài về chương trình này</a></li>
                                @endcan
                                @if($canReport)
                                    <li><a class="dropdown-item" href="{{ route('reports.show', $batch) }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Báo cáo chương trình này</a></li>
                                @endif
                                @if($canCancel)
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="{{ route('batches.cancel', $batch) }}" data-modal><i class="bi bi-x-circle" aria-hidden="true"></i> Hủy chương trình</a></li>
                                @endif
                                @if($canDelete)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('batches.destroy', $batch) }}" data-ajax
                                              data-confirm="Xóa hẳn chương trình nháp này? Thao tác không thể hoàn tác." data-confirm-label="Xóa chương trình nháp">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger" data-loading-text="Đang xóa…"><i class="bi bi-trash" aria-hidden="true"></i> Xóa chương trình nháp</button>
                                        </form>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @if($batch->status !== BatchStatus::Cancelled)
            <div class="border-top px-3 py-3 px-md-4">
                <x-batch-stepper :status="$batch->status" />
            </div>
        @endif
    </section>

    @if($batch->isLocked())
        <div class="alert {{ $batch->status === BatchStatus::Cancelled ? 'alert-secondary' : 'alert-success' }}" role="status">
            <i class="bi bi-lock-fill" aria-hidden="true"></i>
            <div>
                @if($batch->status === BatchStatus::Cancelled)
                    <strong>Chương trình đã hủy</strong> lúc {{ Format::dateTime($batch->cancelled_at) }}.
                    @if($batch->cancellation_reason) Lý do: {{ $batch->cancellation_reason }}. @endif
                @else
                    <strong>Chương trình đã hoàn tất</strong> lúc {{ Format::dateTime($batch->completed_at) }}.
                @endif
                Dữ liệu đang được khóa; điều phối viên có thể mở lại nếu cần chỉnh sửa.
            </div>
        </div>
    @endif

    {{-- Số liệu nhanh của chương trình --}}
    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-lg-3">
            <x-stat label="Công việc" icon="bi-list-check" :tone="$blockedCount ? 'red' : 'green'" :value="$doneCount.'/'.$taskTotal"
                    :hint="$taskTotal ? $percent.'% đã xong'.($blockedCount ? ' · '.$blockedCount.' đang bị vướng' : '') : 'Chưa có công việc'" href="#cong-viec" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat label="Nguyên liệu" icon="bi-basket" :tone="$shortageCount ? 'red' : 'green'" :value="$batch->ingredients->count()"
                    :hint="$shortageCount ? $shortageCount.' nguyên liệu đang báo thiếu' : 'Không có nguyên liệu nào đang báo thiếu'" href="#nguyen-lieu" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat label="Suất đã phân phát" icon="bi-box2-heart" tone="blue" :value="Format::number($batch->distributed_servings, '—')"
                    :empty="$batch->distributed_servings === null" :hint="'Dự kiến '.Format::number($batch->expected_servings).' suất'" href="#ket-qua" />
        </div>
        <div class="col-6 col-lg-3">
            @if($canViewFinancials)
                <x-stat label="Chi phí thực tế" icon="bi-cash-coin" tone="amber" compact :value="$batch->expenseItems->isEmpty() ? '—' : Format::vnd($expenseTotal)"
                        :empty="$batch->expenseItems->isEmpty()" :hint="$batch->expenseItems->count().' khoản chi'" href="#chi-phi" />
            @else
                <x-stat label="Suất dự kiến" icon="bi-people" tone="gray" :value="Format::number($batch->expected_servings)" hint="Theo kế hoạch" />
            @endif
        </div>
    </div>

    {{-- Mục lục (điện thoại) --}}
    <nav class="section-nav d-print-none" aria-label="Các phần của chương trình">
        <a href="#cong-viec"><i class="bi bi-list-check" aria-hidden="true"></i> Công việc</a>
        <a href="#nguyen-lieu"><i class="bi bi-basket" aria-hidden="true"></i> Nguyên liệu @if($shortageCount)<span class="badge text-bg-danger">{{ $shortageCount }} thiếu</span>@endif</a>
        <a href="#ket-qua"><i class="bi bi-box2-heart" aria-hidden="true"></i> Kết quả</a>
        @if($canViewFinancials)
            <a href="#chi-phi"><i class="bi bi-cash-coin" aria-hidden="true"></i> Chi phí</a>
            <a href="#lich-su"><i class="bi bi-clock-history" aria-hidden="true"></i> Lịch sử</a>
        @endif
    </nav>

    <div class="row g-3 g-lg-4">
        {{-- Cột chính --}}
        <div class="col-lg-7 col-xl-8 d-flex flex-column gap-3 gap-lg-4">
            {{-- Công việc --}}
            <section id="cong-viec" class="card scroll-mt" aria-labelledby="h-cong-viec">
                <div class="panel-header">
                    <h2 id="h-cong-viec" class="panel-title"><i class="bi bi-list-check" aria-hidden="true"></i> Công việc <span class="panel-count">{{ $doneCount }}/{{ $taskTotal }} đã xong</span></h2>
                    @can('manageChecklist', $batch)
                        <a href="{{ route('batches.tasks.create', $batch) }}" data-modal class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm công việc</a>
                    @endcan
                </div>
                @if($batch->tasks->isEmpty())
                    <x-empty-state icon="bi-list-task" title="Chưa có công việc" compact>
                        Thêm các việc như đi chợ, sơ chế, nấu, chia suất, dọn dẹp và giao cho từng người.
                    </x-empty-state>
                @else
                    <div class="card-body border-bottom py-3">
                        <x-chart.stack unit="việc" caption="Công việc theo trạng thái" :segments="$taskSegments" />
                    </div>
                    <div>
                        @foreach($batch->tasks as $task)
                            @include('tasks._item', ['task' => $task, 'showBatch' => false])
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Nguyên liệu --}}
            <section id="nguyen-lieu" class="card scroll-mt" aria-labelledby="h-nguyen-lieu">
                <div class="panel-header">
                    <h2 id="h-nguyen-lieu" class="panel-title"><i class="bi bi-basket" aria-hidden="true"></i> Nguyên liệu <span class="panel-count">{{ $batch->ingredients->count() }}</span></h2>
                    @can('manageChecklist', $batch)
                        <a href="{{ route('batches.ingredients.create', $batch) }}" data-modal class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm nguyên liệu</a>
                    @endcan
                </div>
                @if($batch->ingredients->isEmpty())
                    <x-empty-state icon="bi-basket" title="Chưa có nguyên liệu" compact>
                        Danh sách nguyên liệu giúp mọi người biết cần chuẩn bị gì.
                    </x-empty-state>
                @else
                    <div class="callout callout-muted mx-3 mt-3"><i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>Lượng dự kiến là kế hoạch, không phải lượng đã có sẵn hay đã mua.</span></div>
                    @if($canViewFinancials)
                        @php $ingredientCost = $batch->ingredientCostVnd(); @endphp
                        <div class="d-flex flex-wrap justify-content-between gap-2 mx-3 mt-2 small">
                            <span>Tổng tiền nguyên liệu:
                                <strong class="tabular">{{ Format::vnd($ingredientCost) }}</strong></span>
                            <span class="text-body-secondary">
                                {{ $batch->ingredients->whereNotNull('unit_price_vnd')->count() }}/{{ $batch->ingredients->count() }} nguyên liệu có đơn giá · ước tính, không gồm các khoản chi khác
                            </span>
                        </div>
                    @endif
                    <div class="mt-2">
                        @foreach($batch->ingredients as $ingredient)
                            @include('ingredients._item', ['ingredient' => $ingredient])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Cột phụ --}}
        <div class="col-lg-5 col-xl-4 d-flex flex-column gap-3 gap-lg-4">
            @include('batches._channels')
            @include('batches._sponsors')

            {{-- Kết quả --}}
            <section id="ket-qua" class="card scroll-mt" aria-labelledby="h-ket-qua">
                <div class="panel-header">
                    <h2 id="h-ket-qua" class="panel-title"><i class="bi bi-box2-heart" aria-hidden="true"></i> Kết quả số suất</h2>
                    @can('recordResults', $batch)
                        <a href="{{ route('batches.results.edit', $batch) }}" data-modal class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square" aria-hidden="true"></i> Nhập số suất</a>
                    @endcan
                </div>
                <div class="card-body">
                    <x-chart.bars :rows="$servingRows" caption="Số suất dự kiến, đã chuẩn bị và đã phân phát" class="mb-3" />
                    @foreach($batch->servingVariances() as $variance)
                        <div class="callout callout-{{ $variance['level'] }} mb-2" role="note">
                            <i class="bi {{ $variance['level'] === App\Models\CookingBatch::VARIANCE_WARNING ? 'bi-exclamation-triangle' : 'bi-info-circle' }}" aria-hidden="true"></i>
                            <span>{{ $variance['message'] }}</span>
                        </div>
                    @endforeach
                    @if($batch->variance_note)
                        <p class="small mb-2"><strong>Giải thích:</strong> <span class="text-pre-line">{{ $batch->variance_note }}</span></p>
                    @endif
                    <p class="small text-body-secondary mb-0">
                        @if($batch->results_updated_at)
                            Cập nhật lần cuối bởi {{ $batch->resultsUpdater?->name ?? '—' }} lúc {{ Format::dateTime($batch->results_updated_at) }}.
                        @else
                            Chưa có ai nhập số suất thực tế.
                        @endif
                    </p>
                </div>
            </section>

            @if($canViewFinancials)
                {{-- Chi phí --}}
                <section id="chi-phi" class="card scroll-mt" aria-labelledby="h-chi-phi">
                    <div class="panel-header">
                        <h2 id="h-chi-phi" class="panel-title"><i class="bi bi-cash-coin" aria-hidden="true"></i> Chi phí nguyên liệu</h2>
                        @can('manageExpenses', $batch)
                            <a href="{{ route('batches.expenses.create', $batch) }}" data-modal class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm khoản chi</a>
                        @endcan
                    </div>
                    <div class="card-body">
                        <x-chart.bars :rows="$costRows" format="vnd" caption="Chi phí dự kiến và thực tế" />
                        @if(count($expenseRows) >= 3)
                            <h3 class="h6 small fw-bold text-body-secondary text-uppercase mt-4 mb-2" style="letter-spacing: .04em">Theo khoản chi</h3>
                            <x-chart.bars :rows="$expenseRows" format="vnd" caption="Các khoản chi lớn nhất" />
                        @endif
                    </div>
                    @if($batch->expenseItems->isEmpty())
                        <x-empty-state icon="bi-receipt" title="Chưa nhập khoản chi nào" compact>
                            “Chưa nhập” khác với 0 đồng. Nếu chương trình này không tốn chi phí, có thể nhập một khoản 0 đồng kèm ghi chú.
                        </x-empty-state>
                    @else
                        <ul class="list-group list-group-flush border-top">
                            @foreach($batch->expenseItems as $expense)
                                <li class="list-group-item d-flex gap-2 align-items-start">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="fw-semibold">{{ $expense->name }}</span>
                                            <span class="fw-semibold text-nowrap tabular">{{ Format::vnd($expense->amount_vnd) }}</span>
                                        </div>
                                        @if($expense->note)<div class="small">{{ $expense->note }}</div>@endif
                                        <div class="small text-body-secondary">{{ $expense->recorder?->name }} · {{ Format::dateTime($expense->created_at) }}</div>
                                    </div>
                                    @can('update', $expense)
                                        <x-row-menu :label="$expense->name" :edit-url="route('expenses.edit', $expense)"
                                                    :delete-url="route('expenses.destroy', $expense)" :confirm="'Xóa khoản chi “'.$expense->name.'”?'" />
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="panel-footer">Ghi nhận nội bộ, không phải sổ sách kế toán.</div>
                </section>
            @endif

            @if($batch->notes || $batch->completion_notes || ($batch->reopen_reason && $canEdit))
                <section class="card" aria-labelledby="h-ghi-chu">
                    <div class="panel-header"><h2 id="h-ghi-chu" class="panel-title"><i class="bi bi-journal-text" aria-hidden="true"></i> Ghi chú</h2></div>
                    <div class="card-body">
                        @if($batch->notes)
                            <p class="text-pre-line mb-2">{{ $batch->notes }}</p>
                        @endif
                        @if($batch->completion_notes)
                            <h3 class="h6 mt-2">Rút kinh nghiệm</h3>
                            <p class="text-pre-line mb-2">{{ $batch->completion_notes }}</p>
                        @endif
                        {{-- Lý do mở lại chỉ hiện cho người quản lý chương trình (không nằm trong lịch sử thay đổi). --}}
                        @if($batch->reopen_reason && $canEdit)
                            <h3 class="h6 mt-2">Lý do mở lại gần nhất @if($batch->reopened_at)<span class="small text-body-secondary fw-normal">({{ Format::dateTime($batch->reopened_at) }})</span>@endif</h3>
                            <p class="text-pre-line mb-0">{{ $batch->reopen_reason }}</p>
                        @endif
                    </div>
                </section>
            @endif

            @if($canViewFinancials)
                {{-- Lịch sử --}}
                <section id="lich-su" class="card scroll-mt" aria-labelledby="h-lich-su">
                    <div class="panel-header"><h2 id="h-lich-su" class="panel-title"><i class="bi bi-clock-history" aria-hidden="true"></i> Lịch sử thay đổi quan trọng</h2></div>
                    @if($activity->isEmpty())
                        <x-empty-state icon="bi-clock-history" title="Chưa có lịch sử" compact />
                    @else
                        <ol class="timeline">
                            @foreach($activity as $log)
                                <li>
                                    <time datetime="{{ $log->created_at->toIso8601String() }}">{{ Format::dateTime($log->created_at) }}</time>
                                    <strong>{{ $log->user?->name ?? 'Hệ thống' }}</strong>: {{ $log->summary }}
                                </li>
                            @endforeach
                        </ol>
                        <div class="panel-footer">Hiển thị 20 thay đổi gần nhất.</div>
                    @endif
                </section>
            @endif

            <p class="small text-body-secondary mb-0">Tạo bởi {{ $batch->creator?->name }} lúc {{ Format::dateTime($batch->created_at) }}.</p>
        </div>
    </div>
</div>
</x-layouts.app>
