<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Support\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Thêm/sửa nguyên liệu của chương trình. */
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
     * Đơn vị "Khác" lấy từ ô tự nhập; số lượng "1.500,5" → "1500.5"; giá "25.000" → "25000".
     */
    protected function prepareForValidation(): void
    {
        $unit = $this->input('unit') === Ingredient::UNIT_OTHER ? $this->input('unit_other') : $this->input('unit');

        $this->merge([
            'unit' => is_string($unit) ? trim($unit) : $unit,
            'expected_quantity' => Input::quantity($this->input('expected_quantity')),
            'actual_quantity' => Input::quantity($this->input('actual_quantity')),
            'unit_price_vnd' => Input::money($this->input('unit_price_vnd')),
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
            'unit_price_vnd' => ['nullable', 'integer', 'min:0', 'max:999999999'],
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
            'unit_price_vnd' => 'đơn giá',
            'notes' => 'ghi chú',
        ];
    }
}
