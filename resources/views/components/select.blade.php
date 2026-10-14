@props(['name', 'label', 'options' => [], 'value' => null, 'help' => null, 'required' => false, 'placeholder' => null, 'id' => null])
@php
    $id = $id ?? 'f_'.$name;
    $hasError = $errors->has($name);
    $describedBy = trim(($help ? $id.'_help ' : '').($hasError ? $id.'_error' : ''));
    $selected = (string) old($name, $value);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">
        {{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden">(bắt buộc)</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}"
            @if($required) required @endif
            @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if($hasError) aria-invalid="true" @endif
            {{ $attributes->class(['form-select', 'is-invalid' => $hasError]) }}>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if($help)<div id="{{ $id }}_help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div id="{{ $id }}_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
