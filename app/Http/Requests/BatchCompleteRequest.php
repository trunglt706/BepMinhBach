<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Hoàn tất (khóa) đợt nấu — bắt buộc tick xác nhận. */
class BatchCompleteRequest extends FormRequest
{
    /**
     * Xem CookingBatchPolicy::complete.
     */
    public function authorize(): bool
    {
        Gate::authorize('complete', $this->route('batch'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'completion_notes' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => 'Vui lòng đánh dấu ô xác nhận trước khi hoàn tất đợt.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['completion_notes' => 'ghi chú rút kinh nghiệm'];
    }
}
