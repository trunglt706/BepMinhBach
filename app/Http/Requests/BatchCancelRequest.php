<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Hủy chương trình (giữ dữ liệu) — bắt buộc tick xác nhận. */
class BatchCancelRequest extends FormRequest
{
    /**
     * Xem CookingBatchPolicy::cancel.
     */
    public function authorize(): bool
    {
        Gate::authorize('cancel', $this->route('batch'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => 'Vui lòng đánh dấu ô xác nhận trước khi hủy chương trình.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['cancellation_reason' => 'lý do hủy'];
    }
}
