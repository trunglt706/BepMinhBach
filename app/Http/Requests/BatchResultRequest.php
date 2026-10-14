<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Nhập số suất thực tế của đợt nấu (có số phiên bản để tránh hai người ghi đè nhau). */
class BatchResultRequest extends FormRequest
{
    /**
     * Người ghi nhận trở lên, đợt chưa khóa (xem CookingBatchPolicy::recordResults).
     */
    public function authorize(): bool
    {
        Gate::authorize('recordResults', $this->route('batch'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prepared_servings' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'distributed_servings' => ['nullable', 'integer', 'min:0', 'max:100000'],
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
}
