<?php

namespace App\Http\Requests;

use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Thêm/sửa khoản chi của đợt nấu (số tiền VND nguyên, không âm). */
class ExpenseItemRequest extends FormRequest
{
    /**
     * Sửa: ExpenseItemPolicy::update; thêm: CookingBatchPolicy::manageExpenses.
     */
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        $expense
            ? Gate::authorize('update', $expense)
            : Gate::authorize('manageExpenses', $this->route('batch'));

        return true;
    }

    /**
     * Chuẩn hóa số tiền "1.500.000 đ" → "1500000" trước khi kiểm tra.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['amount_vnd' => Input::money($this->input('amount_vnd'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'amount_vnd' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tên khoản chi',
            'amount_vnd' => 'số tiền',
            'note' => 'ghi chú',
        ];
    }
}
