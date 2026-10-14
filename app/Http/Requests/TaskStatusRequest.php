<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Cập nhật nhanh trạng thái công việc; "Đang bị vướng" bắt buộc có lý do. */
class TaskStatusRequest extends FormRequest
{
    /**
     * Người được giao hoặc điều phối viên (xem TaskPolicy::updateStatus).
     */
    public function authorize(): bool
    {
        Gate::authorize('updateStatus', $this->route('task'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'blocked_note' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('status') === TaskStatus::Blocked->value),
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['blocked_note.required' => 'Vui lòng ghi ngắn gọn lý do đang bị vướng.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['blocked_note' => 'lý do bị vướng'];
    }

    /**
     * Trạng thái mới đã kiểm tra.
     */
    public function status(): TaskStatus
    {
        return TaskStatus::from($this->validated('status'));
    }
}
