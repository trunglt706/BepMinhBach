<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Thêm/sửa công việc của chương trình (giao cho tài khoản đang hoạt động). */
class TaskRequest extends FormRequest
{
    /**
     * Sửa: TaskPolicy::update; thêm: CookingBatchPolicy::manageChecklist.
     */
    public function authorize(): bool
    {
        $task = $this->route('task');
        $task
            ? Gate::authorize('update', $task)
            : Gate::authorize('manageChecklist', $this->route('batch'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'tên việc',
            'description' => 'mô tả',
            'assignee_id' => 'người phụ trách',
            'due_at' => 'hạn hoàn thành',
            'notes' => 'ghi chú',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assignee_id.exists' => 'Người phụ trách phải là tài khoản đang hoạt động.',
        ];
    }
}
