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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Công việc (checklist) của đợt nấu và cập nhật nhanh trạng thái. */
class TaskController extends Controller
{
    /**
     * Form thêm công việc.
     *
     * @param  CookingBatch  $batch  đợt nấu
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
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function store(TaskRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $task = new Task($request->validated());
        $task->created_by = $request->user()->id;
        $batch->tasks()->save($task);

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
        $task->update($request->validated());

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

        $task->delete();

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
        $task->applyStatus($status, $request->user(), $request->validated('blocked_note'));
        $task->save();

        if (in_array($status, [TaskStatus::Done, TaskStatus::Blocked], true)) {
            ActivityLog::record($task->batch, $request->user(), ActivityAction::TaskStatusChanged,
                'Việc “'.$task->title.'”: '.$status->label().'.');
        }

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
}
