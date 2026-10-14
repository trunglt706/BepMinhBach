@php
    use App\Support\Format;
    $user = auth()->user();
    $isCoordinator = $user->isCoordinator();
@endphp
<x-layouts.app title="Tổng quan">
    <x-page-header :title="'Xin chào, '.$user->name" :subtitle="Format::longDate(now())">
        @can('create', App\Models\CookingBatch::class)
            @if($hasBatches)
                <x-slot:actions>
                    <a href="{{ route('batches.create') }}" data-modal class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tạo đợt nấu</a>
                </x-slot:actions>
            @endif
        @endcan
    </x-page-header>

    @unless($hasBatches)
        <div class="card mb-4">
            <x-empty-state icon="bi-egg-fried" title="Chưa có đợt nấu ăn nào">
                Mỗi đợt nấu gồm thời gian, số suất dự kiến, danh sách nguyên liệu và công việc cần làm.
                @cannot('create', App\Models\CookingBatch::class)
                    Khi điều phối viên tạo đợt nấu và giao việc, bạn sẽ thấy ở đây.
                @endcannot
                @can('create', App\Models\CookingBatch::class)
                    <x-slot:action>
                        <a href="{{ route('batches.create') }}" data-modal class="btn btn-primary btn-lg"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tạo đợt nấu đầu tiên</a>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        </div>
    @endunless

    {{-- Số liệu nhanh --}}
    <section class="mb-4" aria-labelledby="stats-title">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 id="stats-title" class="h6 text-body-secondary text-uppercase small fw-bold mb-0" style="letter-spacing: .05em">Số liệu nhanh</h2>
            <nav class="segmented" aria-label="Khoảng thời gian cho số suất đã phân phát">
                @foreach($periods as $value => $label)
                    <a href="{{ route('dashboard', ['days' => $value]) }}" @class(['active' => $days === $value]) @if($days === $value) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <div class="row g-2 g-md-3">
            <div class="col-6 col-xl-3">
                <x-stat label="Đợt sắp tới" icon="bi-calendar-check" tone="blue" :value="$upcomingCount"
                        hint="Nháp hoặc đã lên kế hoạch" :href="route('batches.index')" />
            </div>
            <div class="col-6 col-xl-3">
                <x-stat label="Đang diễn ra" icon="bi-fire" tone="amber" :value="$inProgressCount"
                        hint="Đợt đang nấu" :href="route('batches.index', ['status' => 'in_progress'])" />
            </div>
            <div class="col-6 col-xl-3">
                <x-stat :label="$isCoordinator ? 'Việc chưa xong' : 'Việc của tôi chưa xong'" icon="bi-list-check" tone="green" :value="$openTasksCount"
                        :hint="$isCoordinator ? 'Trong các đợt đang mở' : 'Bấm để xem và cập nhật'" :href="$isCoordinator ? null : route('my-tasks')" />
            </div>
            <div class="col-6 col-xl-3">
                @if($distributedBatchCount > 0)
                    <x-stat label="Suất đã phân phát" icon="bi-box2-heart" tone="green" :value="Format::number((int) $distributedTotal)"
                            :hint="$distributedBatchCount.' đợt có số liệu · '.mb_strtolower($periods[$days])" />
                @else
                    <x-stat label="Suất đã phân phát" icon="bi-box2-heart" tone="gray" value="—" empty
                            :hint="'Chưa có số liệu · '.mb_strtolower($periods[$days])" />
                @endif
            </div>
        </div>
    </section>

    {{-- Cảnh báo --}}
    @if($shortages->isNotEmpty() || $blockedTasks->isNotEmpty())
        <section class="card mb-4 border-danger-subtle" aria-labelledby="alerts-title">
            <div class="panel-header">
                <h2 id="alerts-title" class="panel-title"><i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i> Cần xử lý</h2>
                <span class="panel-count">{{ $shortages->count() + $blockedTasks->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @foreach($shortages as $ingredient)
                    <a href="{{ route('batches.show', $ingredient->batch) }}#nguyen-lieu" class="list-group-item list-group-item-action d-flex gap-3 py-3">
                        <span class="stat-icon tone-red"><i class="bi bi-basket" aria-hidden="true"></i></span>
                        <span class="min-w-0">
                            <span class="d-block"><span class="fw-semibold">Thiếu nguyên liệu:</span> {{ $ingredient->name }}</span>
                            <span class="d-block small text-body-secondary">{{ $ingredient->batch->name }}@if($ingredient->restock_note) · {{ $ingredient->restock_note }}@endif</span>
                        </span>
                        <i class="bi bi-chevron-right ms-auto align-self-center text-body-secondary" aria-hidden="true"></i>
                    </a>
                @endforeach
                @foreach($blockedTasks as $task)
                    <a href="{{ route('batches.show', $task->batch) }}#cong-viec" class="list-group-item list-group-item-action d-flex gap-3 py-3">
                        <span class="stat-icon tone-red"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i></span>
                        <span class="min-w-0">
                            <span class="d-block"><span class="fw-semibold">Việc bị vướng:</span> {{ $task->title }}</span>
                            <span class="d-block small text-body-secondary">{{ $task->batch->name }} · {{ $task->assignee?->name ?? 'Chưa giao' }}@if($task->blocked_note): {{ $task->blocked_note }}@endif</span>
                        </span>
                        <i class="bi bi-chevron-right ms-auto align-self-center text-body-secondary" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="row g-3 g-lg-4">
        <div class="col-lg-7 col-xl-8">
            <section class="card h-100" aria-labelledby="active-title">
                <div class="panel-header">
                    <h2 id="active-title" class="panel-title"><i class="bi bi-calendar3" aria-hidden="true"></i> Đợt sắp tới và đang diễn ra</h2>
                    <a href="{{ route('batches.index') }}" class="small fw-semibold">Xem tất cả <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                @if($activeBatches->isEmpty())
                    <x-empty-state icon="bi-calendar" title="Không có đợt nào đang mở" compact>
                        Các đợt nháp, đã lên kế hoạch hoặc đang diễn ra sẽ hiện ở đây.
                    </x-empty-state>
                @else
                    <div>
                        @foreach($activeBatches as $batch)
                            <a href="{{ route('batches.show', $batch) }}" class="batch-row">
                                <x-date-tile :date="$batch->starts_at" />
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="fw-bold">{{ $batch->name }}</span>
                                        <x-status-badge :status="$batch->status" />
                                    </div>
                                    <div class="meta-inline mb-2">
                                        <span><i class="bi bi-clock" aria-hidden="true"></i> {{ $batch->starts_at->format('H:i') }}</span>
                                        <span><i class="bi bi-box2-heart" aria-hidden="true"></i> {{ Format::number($batch->expected_servings) }} suất dự kiến</span>
                                    </div>
                                    <x-task-progress :done="$batch->tasks_done_count" :total="$batch->tasks_count" />
                                </div>
                                <i class="bi bi-chevron-right text-body-secondary d-none d-sm-inline" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div @class(['col-lg-5 col-xl-4 d-flex flex-column gap-3 gap-lg-4', 'order-first order-lg-0' => ! $isCoordinator && $myTasks->isNotEmpty()])>
            <section class="card" aria-labelledby="mine-title">
                <div class="panel-header">
                    <h2 id="mine-title" class="panel-title"><i class="bi bi-check2-square" aria-hidden="true"></i> Việc của tôi</h2>
                    <a href="{{ route('my-tasks') }}" class="small fw-semibold">Mở trang việc của tôi</a>
                </div>
                @if($myTasks->isEmpty())
                    <x-empty-state icon="bi-check2-all" title="Bạn chưa có việc nào cần làm" compact />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($myTasks as $task)
                            <li class="list-group-item d-flex gap-3 py-3">
                                <span class="status-icon is-{{ $task->status->value }}"><i class="bi {{ $task->status->icon() }}" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <div class="small text-body-secondary">
                                        {{ $task->batch->name }}
                                        @if($task->due_at) · <span @class(['text-danger fw-semibold' => $task->isOverdue()])>{{ Format::relative($task->due_at) }}@if($task->isOverdue()) (quá hạn)@endif</span>@endif
                                    </div>
                                    <x-task-status :status="$task->status" class="mt-1" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card" aria-labelledby="latest-title">
                <div class="panel-header">
                    <h2 id="latest-title" class="panel-title"><i class="bi bi-box2-heart" aria-hidden="true"></i> Số suất gần nhất</h2>
                </div>
                <div class="card-body">
                    @if($latestResult)
                        <a href="{{ route('batches.show', $latestResult) }}" class="fw-semibold">{{ $latestResult->name }}</a>
                        <div class="small text-body-secondary mb-3">{{ Format::dateTime($latestResult->starts_at) }}</div>
                        <div class="serving-grid">
                            <div><div class="serving-label">Dự kiến</div><div class="serving-value">{{ Format::number($latestResult->expected_servings) }}</div></div>
                            <div><div class="serving-label">Đã chuẩn bị</div><div @class(['serving-value', 'is-empty' => $latestResult->prepared_servings === null])>{{ Format::number($latestResult->prepared_servings) }}</div></div>
                            <div><div class="serving-label">Đã phân phát</div><div @class(['serving-value', 'is-empty' => $latestResult->distributed_servings === null])>{{ Format::number($latestResult->distributed_servings) }}</div></div>
                        </div>
                    @else
                        <p class="text-body-secondary mb-0">Chưa có đợt nào được ghi nhận số suất thực tế.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
