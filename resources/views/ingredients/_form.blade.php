@csrf
@php
    use App\Models\Ingredient;
    $units = Ingredient::UNITS;
    $currentUnit = old('unit', $ingredient->unit);
    $isCustom = $currentUnit !== null && $currentUnit !== '' && ! in_array($currentUnit, $units, true);
    $selectedUnit = old('unit') === Ingredient::UNIT_OTHER || $isCustom ? Ingredient::UNIT_OTHER : ($currentUnit ?? Ingredient::DEFAULT_UNIT);
    $otherValue = old('unit_other', $isCustom ? $currentUnit : '');
@endphp
<x-input name="name" label="Tên nguyên liệu" :value="$ingredient->name" required maxlength="150" help="Ví dụ: Gạo, Đậu hũ, Rau muống" />
<div class="row">
    <div class="col-sm-6">
        <x-input name="expected_quantity" label="Lượng dự kiến" inputmode="decimal" required
                 :value="App\Support\Format::quantityInput($ingredient->expected_quantity)" help="Có thể nhập số thập phân, ví dụ 2,5." />
    </div>
    <div class="col-sm-6">
        <div class="mb-3">
            <label for="f_unit" class="form-label fw-semibold">Đơn vị <span class="text-danger" aria-hidden="true">*</span></label>
            <select id="f_unit" name="unit" class="form-select @error('unit') is-invalid @enderror" data-unit-select="unit_other_wrap"
                    data-other-value="{{ Ingredient::UNIT_OTHER }}" required
                    @error('unit') aria-invalid="true" aria-describedby="f_unit_error" @enderror>
                @foreach($units as $unit)
                    <option value="{{ $unit }}" @selected($selectedUnit === $unit)>{{ $unit }}</option>
                @endforeach
                <option value="{{ Ingredient::UNIT_OTHER }}" @selected($selectedUnit === Ingredient::UNIT_OTHER)>Khác (tự nhập)</option>
            </select>
            @error('unit')<div id="f_unit_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div id="unit_other_wrap" class="mb-3">
            <label for="f_unit_other" class="form-label fw-semibold">Đơn vị khác</label>
            <input type="text" id="f_unit_other" name="unit_other" value="{{ $otherValue }}" maxlength="30" class="form-control" placeholder="Ví dụ: mớ, bao 25kg">
            <div class="form-text">Hệ thống không tự quy đổi giữa các đơn vị.</div>
        </div>
    </div>
</div>
<x-input name="actual_quantity" label="Lượng thực tế đã dùng" inputmode="decimal"
         :value="App\Support\Format::quantityInput($ingredient->actual_quantity)" help="Để trống nếu chưa ghi nhận. Cùng đơn vị với lượng dự kiến." />
<x-textarea name="notes" label="Ghi chú" :value="$ingredient->notes" rows="2" maxlength="1000" />
