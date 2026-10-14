<?php

namespace App\Http\Requests;

use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ingredient = $this->route('ingredient');
        $ingredient
            ? Gate::authorize('update', $ingredient)
            : Gate::authorize('manageChecklist', $this->route('batch'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $unit = $this->input('unit') === 'other' ? $this->input('unit_other') : $this->input('unit');

        $this->merge([
            'unit' => is_string($unit) ? trim($unit) : $unit,
            'expected_quantity' => Input::decimal($this->input('expected_quantity')),
            'actual_quantity' => Input::decimal($this->input('actual_quantity')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'expected_quantity' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'actual_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'unit' => ['required', 'string', 'max:30', Rule::notIn(['other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên nguyên liệu',
            'expected_quantity' => 'lượng dự kiến',
            'actual_quantity' => 'lượng thực tế đã dùng',
            'unit' => 'đơn vị',
            'notes' => 'ghi chú',
        ];
    }
}
