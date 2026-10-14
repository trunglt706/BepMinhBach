<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Báo thiếu nguyên liệu (ghi chú ngắn, không bắt buộc). */
class ShortageRequest extends FormRequest
{
    /**
     * Mọi thành viên khi đợt chưa khóa (xem IngredientPolicy::reportShortage).
     */
    public function authorize(): bool
    {
        Gate::authorize('reportShortage', $this->route('ingredient'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['restock_note' => ['nullable', 'string', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['restock_note' => 'ghi chú thiếu nguyên liệu'];
    }
}
