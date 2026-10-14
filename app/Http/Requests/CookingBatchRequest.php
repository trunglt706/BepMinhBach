<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\CookingBatch;
use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CookingBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');
        $batch ? Gate::authorize('update', $batch) : Gate::authorize('create', CookingBatch::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_cost_vnd' => Input::money($this->input('estimated_cost_vnd')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'expected_servings' => ['required', 'integer', 'min:0', 'max:100000'],
            'estimated_cost_vnd' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'coordinator_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->whereIn('role', [Role::Coordinator->value, Role::Admin->value]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'save_as' => [$this->route('batch') ? 'prohibited' : 'required', Rule::in(['draft', 'planned'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên đợt nấu',
            'starts_at' => 'ngày giờ bắt đầu',
            'location_text' => 'địa điểm',
            'expected_servings' => 'số suất dự kiến',
            'estimated_cost_vnd' => 'chi phí dự kiến',
            'coordinator_id' => 'người điều phối',
            'notes' => 'ghi chú',
        ];
    }

    public function messages(): array
    {
        return [
            'coordinator_id.exists' => 'Người điều phối phải là tài khoản điều phối viên hoặc quản trị đang hoạt động.',
        ];
    }
}
