<?php

namespace App\Http\Requests;

use App\Enums\BatchStatus;
use App\Enums\Role;
use App\Models\CookingBatch;
use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Tạo/sửa thông tin đợt nấu. */
class CookingBatchRequest extends FormRequest
{
    /** Trạng thái được chọn khi tạo đợt (nút "Lưu nháp" / "Lưu kế hoạch"). */
    public const CREATE_STATUSES = [BatchStatus::Draft, BatchStatus::Planned];

    /**
     * Sửa: CookingBatchPolicy::update; tạo: CookingBatchPolicy::create.
     */
    public function authorize(): bool
    {
        $batch = $this->route('batch');
        $batch ? Gate::authorize('update', $batch) : Gate::authorize('create', CookingBatch::class);

        return true;
    }

    /**
     * Chuẩn hóa số tiền "1.500.000 đ" → "1500000" trước khi kiểm tra.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_cost_vnd' => Input::money($this->input('estimated_cost_vnd')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
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
                    ->whereIn('role', Role::valuesAtLeast(Role::Coordinator)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'save_as' => [
                $this->route('batch') ? 'prohibited' : 'required',
                Rule::enum(BatchStatus::class)->only(self::CREATE_STATUSES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'coordinator_id.exists' => 'Người điều phối phải là tài khoản điều phối viên hoặc quản trị đang hoạt động.',
        ];
    }
}
