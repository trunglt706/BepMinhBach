<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\TaskStatusRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Công việc (checklist) của chương trình và cập nhật nhanh trạng thái. */
class TaskController extends Controller
{
    /**
     * Form thêm công việc.
     *
     * @param  CookingBatch  $batch  chương trình
     */
    public function create(CookingBatch $batch): View
    {
        Gate::authorize('manageChecklist', $batch);

        return view('tasks.create', [
            'batch' => $batch,
            'task' => new Task,
            'assignees' => $this->assigneeOptions(),
        ]);
    }

    /**
     * Thêm công việc; "Lưu và thêm tiếp" giữ modal mở.
     *
     * @param  TaskRequest  $request  dữ liệu đã kiểm tra + quyền manageChecklist
     * @param  CookingBatch  $batch  chương trình
     */
    public function store(TaskRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $task = new Task($request->validated());
        $task->created_by = $request->user()->id;
        DB::transaction(function () use ($batch, $task, $request) {
            $batch->tasks()->save($task);
            ActivityLog::record($batch, $request->user(), ActivityAction::TaskCreated,
                'Thêm việc “'.$task->title.'”'.($task->assignee_id ? ' (có người phụ trách).' : '.'),
                'Thêm việc “'.$task->title.'”'.$this->assigneeText($task).$this->dueText($task).'.');
            $this->notifyAssignee($task, $request->user());
        });

        if ($request->boolean('add_another')) {
            return $this->done($request, redirect()->route('batches.tasks.create', $batch), 'Đã thêm việc “'.$task->title.'”. Bạn có thể thêm việc tiếp theo.', ['keepOpen' => true]);
        }

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('cong-viec'), 'Đã thêm việc “'.$task->title.'”.');
    }

    /**
     * Form sửa / phân công lại công việc.
     *
     * @param  Task  $task  công việc
     */
    public function edit(Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', [
            'batch' => $task->batch,
            'task' => $task,
            'assignees' => $this->assigneeOptions($task->assignee_id),
        ]);
    }

    /**
     * Lưu thay đổi công việc.
     *
     * @param  TaskRequest  $request  dữ liệu đã kiểm tra + quyền update
     * @param  Task  $task  công việc
     */
    public function update(TaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($task, $request) {
            $task->fill($request->validated());
            $reassigned = $task->isDirty('assignee_id');
            // Đổi tên/hạn được báo riêng; ghi chú (chữ tự do) không gửi ra kênh.
            $edited = $task->isDirty(['title', 'due_at']);
            $oldTitle = $task->getOriginal('title');
            $task->save();

            if ($edited) {
                ActivityLog::record($task->batch, $request->user(), ActivityAction::TaskUpdated,
                    'Sửa việc “'.$task->title.'”'.($oldTitle !== $task->title ? ' (trước đây: “'.$oldTitle.'”)' : '').$this->dueText($task).'.');
            }
            if ($reassigned) {
                ActivityLog::record($task->batch, $request->user(), ActivityAction::TaskAssigned,
                    $task->assignee_id ? 'Đổi người phụ trách việc “'.$task->title.'”.' : 'Bỏ người phụ trách việc “'.$task->title.'”.',
                    $task->assignee_id
                        ? 'Giao việc “'.$task->title.'”'.$this->assigneeText($task).$this->dueText($task).'.'
                        : 'Việc “'.$task->title.'” chưa có người phụ trách.');
                $this->notifyAssignee($task, $request->user());
            }
        });

        return $this->done($request, redirect()->route('batches.show', $task->cooking_batch_id)->withFragment('cong-viec'), 'Đã lưu việc “'.$task->title.'”.');
    }

    /**
     * Xóa công việc.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Task  $task  công việc
     */
    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $task);

        DB::transaction(function () use ($task, $request) {
            $task->delete();
            ActivityLog::record($task->batch, $request->user(), ActivityAction::TaskDeleted, 'Xóa việc “'.$task->title.'”.');
        });

        return $this->done($request, redirect()->route('batches.show', $task->cooking_batch_id)->withFragment('cong-viec'), 'Đã xóa việc “'.$task->title.'”.');
    }

    /**
     * Cập nhật nhanh: Đang làm / Hoàn thành / Đang bị vướng (bắt buộc lý do) / Chưa làm.
     * Log chỉ ghi tên việc + trạng thái; lý do vướng (chữ tự do) không đưa vào log.
     *
     * @param  TaskStatusRequest  $request  trạng thái mới + quyền updateStatus
     * @param  Task  $task  công việc
     */
    public function updateStatus(TaskStatusRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $status = $request->status();
        $previous = $task->status;
        DB::transaction(function () use ($task, $status, $previous, $request) {
            $task->applyStatus($status, $request->user(), $request->validated('blocked_note'));
            $task->save();

            // Mọi lần đổi trạng thái thật sự đều ghi lịch sử + báo kênh (bấm lại đúng trạng thái cũ thì thôi).
            if ($previous !== $status) {
                ActivityLog::record($task->batch, $request->user(), ActivityAction::TaskStatusChanged,
                    'Việc “'.$task->title.'”: '.$status->label().'.');
            }
        });

        $message = match ($status) {
            TaskStatus::Done => 'Đã đánh dấu hoàn thành “'.$task->title.'”.',
            TaskStatus::Blocked => 'Đã báo đang bị vướng. Điều phối viên sẽ thấy cảnh báo.',
            default => 'Đã cập nhật trạng thái “'.$task->title.'”.',
        };

        return $this->done($request, back(), $message);
    }

    /**
     * Người có thể được giao việc: tài khoản đang hoạt động, luôn giữ người đang được giao.
     *
     * @param  int|null  $currentId  người đang được giao (nếu có)
     * @return Collection<int, User>
     */
    private function assigneeOptions(?int $currentId = null): Collection
    {
        return User::active()
            ->when($currentId, fn ($q) => $q->orWhere('id', $currentId))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'is_active']);
    }

    /**
     * Ghi thông báo "được giao việc" cho người phụ trách (không gửi khi tự giao cho mình).
     *
     * @param  Task  $task  công việc
     * @param  User  $by  người giao
     */
    private function notifyAssignee(Task $task, User $by): void
    {
        if ($task->assignee_id && $task->assignee_id !== $by->id) {
            User::find($task->assignee_id)?->notify(new TaskAssignedNotification($task, $by->name));
        }
    }

    /**
     * " cho <tên>" nếu việc có người phụ trách (chỉ dùng cho nội dung thông báo).
     *
     * @param  Task  $task  công việc
     */
    private function assigneeText(Task $task): string
    {
        $name = $task->assignee_id ? $task->assignee()->value('name') : null;

        return $name ? ' cho '.$name : '';
    }

    /**
     * ", hạn dd/mm HH:ii" nếu việc có hạn.
     *
     * @param  Task  $task  công việc
     */
    private function dueText(Task $task): string
    {
        return $task->due_at ? ', hạn '.$task->due_at->format('H:i d/m') : '';
    }
}
