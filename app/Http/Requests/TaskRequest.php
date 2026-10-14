<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        $task
            ? Gate::authorize('update', $task)
            : Gate::authorize('manageChecklist', $this->route('batch'));

        return true;
    }

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

    public function messages(): array
    {
        return [
            'assignee_id.exists' => 'Người phụ trách phải là tài khoản đang hoạt động.',
        ];
    }
}
