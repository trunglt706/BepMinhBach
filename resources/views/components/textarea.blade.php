@props(['name', 'label', 'value' => null, 'help' => null, 'required' => false, 'rows' => 3, 'id' => null])
@php
    $id = $id ?? 'f_'.$name;
    $hasError = $errors->has($name);
    $describedBy = trim(($help ? $id.'_help ' : '').($hasError ? $id.'_error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">
        {{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden">(bắt buộc)</span>@endif
    </label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
              @if($required) required @endif
              @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
              @if($hasError) aria-invalid="true" @endif
              {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}>{{ old($name, $value) }}</textarea>
    @if($help)<div id="{{ $id }}_help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div id="{{ $id }}_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
