<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Thêm/sửa nguyên liệu của đợt nấu. */
class IngredientRequest extends FormRequest
{
    /**
     * Sửa: IngredientPolicy::update; thêm: CookingBatchPolicy::manageChecklist.
     */
    public function authorize(): bool
    {
        $ingredient = $this->route('ingredient');
        $ingredient
            ? Gate::authorize('update', $ingredient)
            : Gate::authorize('manageChecklist', $this->route('batch'));

        return true;
    }

    /**
     * Đơn vị "Khác" lấy từ ô tự nhập; số lượng "2,5" → "2.5".
     */
    protected function prepareForValidation(): void
    {
        $unit = $this->input('unit') === Ingredient::UNIT_OTHER ? $this->input('unit_other') : $this->input('unit');

        $this->merge([
            'unit' => is_string($unit) ? trim($unit) : $unit,
            'expected_quantity' => Input::decimal($this->input('expected_quantity')),
            'actual_quantity' => Input::decimal($this->input('actual_quantity')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'expected_quantity' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'actual_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'unit' => ['required', 'string', 'max:30', Rule::notIn([Ingredient::UNIT_OTHER])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
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
