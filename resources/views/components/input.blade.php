@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'id' => null, 'suffix' => null, 'money' => false])
@php
    $id = $id ?? 'f_'.$name;
    $hasError = $errors->has($name);
    $describedBy = trim(($help ? $id.'_help ' : '').($hasError ? $id.'_error' : ''));
    $current = old($name, $value);
    if ($money) {
        // Ô tiền: hiển thị sẵn dạng 1.500.000; app.js chỉ cho gõ số, tự định dạng và bỏ dấu chấm trước khi gửi.
        $type = 'text';
        $digits = preg_replace('/\D/', '', (string) $current);
        $current = $digits === '' || strlen($digits) > 15 ? (string) $current : number_format((int) $digits, 0, ',', '.');
        $suffix = $suffix ?? 'đồng';
    }
    // Ô mật khẩu: có nút xem/ẩn (app.js, data-toggle-password). Không điền lại mật khẩu cũ vào ô.
    $isPassword = $type === 'password';
    if ($isPassword) {
        $current = null;
    }
    $grouped = $suffix || $isPassword;
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">
        {{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden">(bắt buộc)</span>@endif
    </label>
    @if($grouped)<div class="input-group @if($hasError) has-validation @endif">@endif
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" @unless($isPassword) value="{{ $current }}" @endunless
           @if($required) required @endif
           @if($money) inputmode="numeric" autocomplete="off" data-money @endif
           @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @if($hasError) aria-invalid="true" @endif
           {{ $attributes->class(['form-control', 'is-invalid' => $hasError, 'tabular' => $money]) }}>
    @if($suffix)<span class="input-group-text fw-semibold">{{ $suffix }}</span>@endif
    @if($isPassword)
        <button type="button" class="btn btn-outline-secondary btn-icon" data-toggle-password="{{ $id }}"
                aria-controls="{{ $id }}" aria-label="Hiện mật khẩu" aria-pressed="false">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    @endif
    @if($grouped)</div>@endif
    @if($help)<div id="{{ $id }}_help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div id="{{ $id }}_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
