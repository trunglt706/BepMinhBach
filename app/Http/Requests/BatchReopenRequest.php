<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Mở lại chương trình đã hoàn tất/đã hủy — bắt buộc ghi lý do và tick xác nhận. */
class BatchReopenRequest extends FormRequest
{
    /**
     * Xem CookingBatchPolicy::reopen.
     */
    public function authorize(): bool
    {
        Gate::authorize('reopen', $this->route('batch'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => 'Vui lòng đánh dấu ô xác nhận trước khi mở lại chương trình.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => 'lý do mở lại'];
    }
}
