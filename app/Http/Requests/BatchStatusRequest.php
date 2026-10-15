<?php

namespace App\Http\Requests;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Chuyển trạng thái đơn giản (Nháp ↔ Đã lên kế hoạch → Đang diễn ra). */
class BatchStatusRequest extends FormRequest
{
    /**
     * Điều phối viên của chương trình (xem CookingBatchPolicy::changeStatus).
     */
    public function authorize(): bool
    {
        Gate::authorize('changeStatus', $this->batch());

        return true;
    }

    /**
     * Chỉ nhận trạng thái được phép đi tới từ trạng thái hiện tại (BatchStatus::simpleTransitions()).
     * Dùng Rule::in thay vì Rule::enum()->only(): only([]) của Laravel nghĩa là "không giới hạn",
     * trong khi danh sách rỗng ở đây phải nghĩa là "không chuyển được sang đâu".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = array_map(fn (BatchStatus $s) => $s->value, $this->batch()->status->simpleTransitions());

        return [
            'to' => ['required', Rule::in($allowed)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to.in' => 'Không thể chuyển chương trình sang trạng thái này từ trạng thái hiện tại.'];
    }

    /**
     * Trạng thái đích đã kiểm tra.
     */
    public function target(): BatchStatus
    {
        return BatchStatus::from($this->validated('to'));
    }

    /**
     * Chương trình trong URL.
     */
    private function batch(): CookingBatch
    {
        return $this->route('batch');
    }
}
