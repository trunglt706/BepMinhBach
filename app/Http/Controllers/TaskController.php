<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function create(CookingBatch $batch): View
    {
        Gate::authorize('manageChecklist', $batch);

        return view('tasks.create', [
            'batch' => $batch,
            'task' => new Task,
            'assignees' => $this->assigneeOptions(),
        ]);
    }

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

    public function edit(Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', [
            'batch' => $task->batch,
            'task' => $task,
            'assignees' => $this->assigneeOptions($task->assignee_id),
        ]);
    }

    public function update(TaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $task->update($request->validated());

        return $this->done($request, redirect()->route('batches.show', $task->cooking_batch_id)->withFragment('cong-viec'), 'Đã lưu việc “'.$task->title.'”.');
    }

    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return $this->done($request, redirect()->route('batches.show', $task->cooking_batch_id)->withFragment('cong-viec'), 'Đã xóa việc “'.$task->title.'”.');
    }

    /** Cập nhật nhanh: Đang làm / Hoàn thành / Đang bị vướng (bắt buộc ghi lý do) / Chưa làm. */
    public function updateStatus(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('updateStatus', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'blocked_note' => ['nullable', 'required_if:status,blocked', 'string', 'max:500'],
        ], [
            'blocked_note.required_if' => 'Vui lòng ghi ngắn gọn lý do đang bị vướng.',
        ], ['blocked_note' => 'lý do bị vướng']);

        $status = TaskStatus::from($validated['status']);
        $task->applyStatus($status, $request->user(), $validated['blocked_note'] ?? null);
        $task->save();

        if (in_array($status, [TaskStatus::Done, TaskStatus::Blocked], true)) {
            ActivityLog::record($task->batch, $request->user(), 'task.status',
                'Việc “'.$task->title.'”: '.$status->label().'.');
        }

        $message = match ($status) {
            TaskStatus::Done => 'Đã đánh dấu hoàn thành “'.$task->title.'”.',
            TaskStatus::Blocked => 'Đã báo đang bị vướng. Điều phối viên sẽ thấy cảnh báo.',
            default => 'Đã cập nhật trạng thái “'.$task->title.'”.',
        };

        return $this->done($request, back(), $message);
    }

    private function assigneeOptions(?int $currentId = null)
    {
        return User::active()
            ->when($currentId, fn ($q) => $q->orWhere('id', $currentId))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'is_active']);
    }
}
