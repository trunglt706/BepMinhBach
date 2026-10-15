@php
    $isEdit = $channel->exists;
    $currentDriver = old('driver', $selected?->value);
    $settingError = fn (string $key) => $errors->first('settings.'.$key);
@endphp
{{--
    Thông số theo loại kênh. Có JS: chỉ hiện (và chỉ gửi) phần của loại đang chọn. Không JS: hiện tất cả,
    máy chủ chỉ kiểm tra/lưu phần của loại đã chọn. Ô bí mật không bao giờ điền sẵn giá trị đã lưu.
--}}
<div data-channel-form>
    @if($isEdit)
        <p class="mb-3"><span class="channel-icon channel-{{ $channel->driver->value }} me-1" aria-hidden="true"><i class="bi {{ $channel->driver->icon() }}"></i></span>
            Kênh <strong>{{ $channel->driver->label() }}</strong> <span class="small text-body-secondary">(muốn đổi loại kênh: xóa kênh này rồi thêm kênh mới)</span></p>
    @else
        <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6">Loại kênh <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden">(bắt buộc)</span></legend>
            <div class="topic-options">
                @foreach($drivers as $d)
                    <label class="topic-option">
                        <input type="radio" name="driver" value="{{ $d->value }}" class="topic-radio" data-channel-driver @checked($currentDriver === $d->value) required>
                        <span class="topic-option-body"><i class="bi {{ $d->icon() }}" aria-hidden="true"></i> {{ $d->label() }}</span>
                    </label>
                @endforeach
            </div>
            @error('driver')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>
    @endif

    @foreach($drivers as $d)
        <fieldset class="channel-fields" data-channel-fields="{{ $d->value }}">
            <legend class="visually-hidden">Thông số {{ $d->label() }}</legend>
            @if(count($drivers) > 1)<div class="channel-fields-title"><i class="bi {{ $d->icon() }}" aria-hidden="true"></i> Thông số {{ $d->label() }}</div>@endif
            @foreach($d->fields() as $key => $field)
                @php
                    $id = 'f_'.$d->value.'_'.$key;
                    $saved = $isEdit ? $channel->displaySetting($key) : null;
                    $keep = $isEdit && $field['secret'] && $saved !== null;
                    $error = $currentDriver === $d->value ? $settingError($key) : null;
                    $value = $field['secret'] ? '' : old('settings.'.$key, $isEdit ? $channel->setting($key) : '');
                @endphp
                <div class="mb-3">
                    <label for="{{ $id }}" class="form-label fw-semibold">{{ $field['label'] }}
                        @unless($keep)<span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden">(bắt buộc)</span>@endunless
                    </label>
                    <input type="{{ $field['secret'] ? 'password' : 'text' }}" id="{{ $id }}" name="settings[{{ $key }}]" value="{{ $value }}"
                           @class(['form-control', 'is-invalid' => $error]) maxlength="300" autocomplete="off" spellcheck="false"
                           placeholder="{{ $keep ? 'Để trống để giữ giá trị đã lưu ('.$saved.')' : $field['placeholder'] }}"
                           @unless($keep) required @endunless
                           aria-describedby="{{ $id }}_help @if($error) {{ $id }}_error @endif">
                    <div id="{{ $id }}_help" class="form-text">{{ $field['help'] }}</div>
                    @if($error)<div id="{{ $id }}_error" class="invalid-feedback d-block">{{ $error }}</div>@endif
                </div>
            @endforeach
            @php($joinId = 'f_'.$d->value.'_join')
            <div class="mb-3">
                <label for="{{ $joinId }}" class="form-label fw-semibold">Link tham gia kênh</label>
                <input type="url" id="{{ $joinId }}" name="join_urls[{{ $d->value }}]" data-error-name="join_url" value="{{ old('join_urls.'.$d->value, $channel->join_url) }}"
                       @class(['form-control', 'is-invalid' => $currentDriver === $d->value && $errors->has('join_url')]) maxlength="500"
                       placeholder="{{ $d->joinUrlExample() }}" aria-describedby="{{ $joinId }}_help">
                <div id="{{ $joinId }}_help" class="form-text">Không bắt buộc nhưng nên có: hiện nút “Tham gia kênh” trên trang chương trình để mọi người tự vào nhận thông báo.</div>
                @if($currentDriver === $d->value)@error('join_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
            </div>
        </fieldset>
    @endforeach

    <div class="form-check form-switch mb-2">
        <input type="hidden" name="is_enabled" value="0">
        <input class="form-check-input" type="checkbox" role="switch" id="f_is_enabled" name="is_enabled" value="1"
               @checked(old('is_enabled', $channel->is_enabled ?? true))>
        <label class="form-check-label" for="f_is_enabled">Gửi thông báo vào kênh này</label>
    </div>
    <p class="small text-body-secondary mb-0"><i class="bi bi-shield-lock" aria-hidden="true"></i>
        Token/webhook được mã hóa khi lưu và không hiện lại. Tin nhắn không chứa chi phí hay ghi chú tự do; có link mở chương trình trong hệ thống.</p>
</div>
