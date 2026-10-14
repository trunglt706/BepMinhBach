{{-- Một công việc với thao tác nhanh. Biến: $task, $showBatch (bool) --}}
@php
    use App\Enums\TaskStatus;
    use App\Support\Format;
    $canUpdateStatus = auth()->user()->can('updateStatus', $task);
    $isThisTaskOld = (string) old('_task') === (string) $task->id;
    $status = $task->status;
@endphp
<div @class(['item-row', 'is-done' => $status === TaskStatus::Done, 'is-blocked' => $status === TaskStatus::Blocked])>
    <span class="status-icon is-{{ $status->value }}"><i class="bi {{ $status === TaskStatus::Done ? 'bi-check-lg' : $status->icon() }}" aria-hidden="true"></i></span>

    <div class="item-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="min-w-0">
                <p class="item-title">{{ $task->title }}</p>
                @if($showBatch ?? false)
                    <a href="{{ route('batches.show', $task->batch) }}#cong-viec" class="small fw-semibold">{{ $task->batch->name }}</a>
                @endif
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <x-task-status :status="$status" />
                @can('update', $task)
                    <x-row-menu :label="$task->title" :edit-url="route('tasks.edit', $task)" edit-label="Sửa / phân công lại"
                                :delete-url="route('tasks.destroy', $task)" :confirm="'Xóa việc “'.$task->title.'”?'" />
                @endcan
            </div>
        </div>

        <div class="meta-inline mt-1">
            <span>
                @if($task->assignee)
                    <x-avatar :name="$task->assignee->name" size="sm" /> {{ $task->assignee->name }}
                @else
                    <i class="bi bi-person-dash" aria-hidden="true"></i> Chưa giao người phụ trách
                @endif
            </span>
            @if($task->due_at)
                <span @class(['text-danger fw-semibold' => $task->isOverdue()])>
                    <i class="bi bi-alarm" aria-hidden="true"></i> Hạn {{ Format::relative($task->due_at) }}@if($task->isOverdue()) (quá hạn)@endif
                </span>
            @endif
        </div>

        @if($task->description)
            <p class="small mt-2 mb-0 text-pre-line">{{ $task->description }}</p>
        @endif
        @if($status === TaskStatus::Blocked && $task->blocked_note)
            <div class="callout callout-danger mt-2"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><span><strong>Lý do:</strong> {{ $task->blocked_note }}</span></div>
        @endif
        @if($status === TaskStatus::Done && $task->completed_at)
            <div class="small mt-1 text-success">
                <i class="bi bi-check2" aria-hidden="true"></i> Hoàn thành lúc {{ Format::dateTime($task->completed_at) }}
                @if($task->completer) bởi {{ $task->completer->name }} @endif
            </div>
        @endif

        @if($canUpdateStatus)
            <div class="item-actions">
                @if($status !== TaskStatus::Done)
                    <form method="POST" action="{{ route('tasks.status', $task) }}" data-ajax>
                        @csrf
                        <input type="hidden" name="status" value="done">
                        <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Đang cập nhật…"><i class="bi bi-check-lg" aria-hidden="true"></i> Đánh dấu hoàn thành</button>
                    </form>
                    @if($status !== TaskStatus::InProgress)
                        <form method="POST" action="{{ route('tasks.status', $task) }}" data-ajax>
                            @csrf
                            <input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" data-loading-text="Đang cập nhật…">
                                <i class="bi bi-play-fill" aria-hidden="true"></i> {{ $status === TaskStatus::Blocked ? 'Hết vướng, làm tiếp' : 'Bắt đầu làm' }}
                            </button>
                        </form>
                    @endif
                    <details class="quick-action" @if($isThisTaskOld && $errors->has('blocked_note')) open @endif>
                        <summary class="btn btn-outline-danger btn-sm"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                            {{ $status === TaskStatus::Blocked ? 'Cập nhật lý do bị vướng' : 'Báo đang bị vướng' }}</summary>
                    <form method="POST" action="{{ route('tasks.status', $task) }}" data-ajax class="quick-form mt-2">
                        @csrf
                        <input type="hidden" name="status" value="blocked">
                        <input type="hidden" name="_task" value="{{ $task->id }}">
                        <label for="blocked_note_{{ $task->id }}" class="form-label small">Đang vướng gì? <span class="required-mark" aria-hidden="true">*</span></label>
                        <textarea id="blocked_note_{{ $task->id }}" name="blocked_note" rows="2" maxlength="500" required
                                  class="form-control @if($isThisTaskOld && $errors->has('blocked_note')) is-invalid @endif"
                                  @if($isThisTaskOld && $errors->has('blocked_note')) aria-describedby="blocked_note_{{ $task->id }}_error" @endif
                                  placeholder="Ví dụ: thiếu gas, chưa có rau">{{ $isThisTaskOld ? old('blocked_note') : $task->blocked_note }}</textarea>
                        @if($isThisTaskOld)
                            @error('blocked_note')<div id="blocked_note_{{ $task->id }}_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @endif
                        <button type="submit" class="btn btn-danger btn-sm mt-2" data-loading-text="Đang gửi…">Gửi báo vướng</button>
                    </form>
                    </details>
                @else
                    <form method="POST" action="{{ route('tasks.status', $task) }}" data-ajax>
                        @csrf
                        <input type="hidden" name="status" value="todo">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" data-loading-text="Đang cập nhật…"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Đánh dấu chưa xong</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
