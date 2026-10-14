<?php

namespace App\Http\Requests;

use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ExpenseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        $expense
            ? Gate::authorize('update', $expense)
            : Gate::authorize('manageExpenses', $this->route('batch'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount_vnd' => Input::money($this->input('amount_vnd'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'amount_vnd' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên khoản chi',
            'amount_vnd' => 'số tiền',
            'note' => 'ghi chú',
        ];
    }
}
