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
            'starts_on' => ['required', 'date_format:Y-m-d'],
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
            // Ảnh nền (slider trang chủ, trang đợt công khai): kiểm tra kiểu theo NỘI DUNG file như ảnh bài viết.
            'cover' => ['nullable', 'file', 'max:'.config('bmb.post_images.max_upload_kb'),
                'mimetypes:'.implode(',', config('bmb.post_images.mimes')), 'mimes:jpg,jpeg,png,webp'],
            'remove_cover' => ['nullable', 'boolean'],
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
            'starts_on' => 'ngày bắt đầu',
            'location_text' => 'địa điểm',
            'expected_servings' => 'số suất dự kiến',
            'estimated_cost_vnd' => 'chi phí dự kiến',
            'coordinator_id' => 'người điều phối',
            'notes' => 'ghi chú',
            'cover' => 'ảnh nền',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $type = 'Chỉ nhận ảnh JPG, PNG hoặc WebP.';

        return [
            'coordinator_id.exists' => 'Người điều phối phải là tài khoản điều phối viên hoặc quản trị đang hoạt động.',
            'cover.max' => 'Ảnh tối đa '.round((int) config('bmb.post_images.max_upload_kb') / 1024).' MB.',
            'cover.mimetypes' => $type, 'cover.mimes' => $type, 'cover.file' => $type,
            'cover.uploaded' => 'Tải ảnh lên không thành công (có thể ảnh quá lớn).',
        ];
    }
}
