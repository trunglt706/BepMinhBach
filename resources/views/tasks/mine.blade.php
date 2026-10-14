@php
    $overdue = $urgent->filter->isOverdue()->count();
    $blocked = $urgent->where('status', App\Enums\TaskStatus::Blocked)->count();
    $total = $urgent->count() + $later->count();
    $summary = $total === 0
        ? 'Bạn không có việc nào đang mở.'
        : "Bạn có {$total} việc đang mở".($overdue ? " · {$overdue} quá hạn" : '').($blocked ? " · {$blocked} đang vướng" : '').'.';
@endphp
<x-layouts.app title="Công việc của tôi">
<div id="my-tasks-page" data-region>
    <x-page-header title="Công việc của tôi" :subtitle="$summary" />

    <div class="callout callout-info mb-3">
        <i class="bi bi-hand-index-thumb" aria-hidden="true"></i>
        <span>Bấm <strong>Đánh dấu hoàn thành</strong> khi xong việc, hoặc <strong>Báo đang bị vướng</strong> nếu cần giúp đỡ.</span>
    </div>

    <div class="row g-3 g-lg-4">
        <div class="col-xl-7">
            <section class="card mb-3 mb-lg-4" aria-labelledby="h-urgent">
                <div class="panel-header">
                    <h2 id="h-urgent" class="panel-title"><i class="bi bi-alarm" aria-hidden="true"></i> Hôm nay, quá hạn hoặc đang vướng <span class="panel-count">{{ $urgent->count() }}</span></h2>
                </div>
                @if($urgent->isEmpty())
                    <x-empty-state icon="bi-emoji-smile" title="Không có việc gấp hôm nay" compact />
                @else
                    <div>
                        @foreach($urgent as $task)
                            @include('tasks._item', ['task' => $task, 'showBatch' => true])
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card" aria-labelledby="h-later">
                <div class="panel-header">
                    <h2 id="h-later" class="panel-title"><i class="bi bi-calendar" aria-hidden="true"></i> Sắp tới và chưa có hạn <span class="panel-count">{{ $later->count() }}</span></h2>
                </div>
                @if($later->isEmpty())
                    <x-empty-state icon="bi-check2-all" title="Không có việc nào khác" compact>
                        Khi điều phối viên giao việc cho bạn, việc sẽ hiện ở đây.
                    </x-empty-state>
                @else
                    <div>
                        @foreach($later as $task)
                            @include('tasks._item', ['task' => $task, 'showBatch' => true])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card" aria-labelledby="h-done">
                <div class="panel-header">
                    <h2 id="h-done" class="panel-title"><i class="bi bi-check-circle" aria-hidden="true"></i> Đã xong trong 7 ngày qua <span class="panel-count">{{ $recentlyDone->count() }}</span></h2>
                </div>
                @if($recentlyDone->isEmpty())
                    <x-empty-state icon="bi-hourglass" title="Chưa có việc nào hoàn thành gần đây" compact />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($recentlyDone as $task)
                            <li class="list-group-item d-flex gap-3 align-items-start">
                                <span class="status-icon is-done"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <div class="small text-body-secondary">
                                        <a href="{{ route('batches.show', $task->batch) }}">{{ $task->batch->name }}</a> · {{ App\Support\Format::relative($task->completed_at) }}
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</div>
</x-layouts.app>
