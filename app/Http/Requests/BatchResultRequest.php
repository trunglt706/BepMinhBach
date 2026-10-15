<?php

namespace App\Http\Requests;

use App\Support\Format;
use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Nhập số suất thực tế của chương trình (có số phiên bản để tránh hai người ghi đè nhau). */
class BatchResultRequest extends FormRequest
{
    /**
     * Người ghi nhận trở lên, chương trình chưa khóa (xem CookingBatchPolicy::recordResults).
     */
    public function authorize(): bool
    {
        Gate::authorize('recordResults', $this->route('batch'));

        return true;
    }

    /**
     * Số suất có dấu chấm hàng nghìn ("1.200") → "1200".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'prepared_servings' => Input::money($this->input('prepared_servings')),
            'distributed_servings' => Input::money($this->input('distributed_servings')),
        ]);
    }

    /**
     * Đã phân phát không được vượt quá đã chuẩn bị (chỉ so khi đã nhập số chuẩn bị).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prepared_servings' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'distributed_servings' => ['nullable', 'integer', 'min:0', 'max:100000',
                Rule::when(is_numeric($this->input('prepared_servings')), 'lte:prepared_servings')],
            'variance_note' => ['nullable', 'string', 'max:1000'],
            'results_version' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prepared_servings' => 'số suất đã chuẩn bị',
            'distributed_servings' => 'số suất đã phân phát',
            'variance_note' => 'giải thích chênh lệch',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'distributed_servings.lte' => 'Số suất đã phân phát không được vượt quá số đã chuẩn bị ('
                .Format::number((int) $this->input('prepared_servings')).').',
        ];
    }
}
