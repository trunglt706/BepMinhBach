@csrf
@php
    use App\Enums\SponsorType;
    $accept = implode(',', config('bmb.post_images.mimes'));
    $logo = $sponsor->logo();
    $types = collect(SponsorType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
@endphp
<x-input name="name" label="Tên đơn vị" :value="$sponsor->name" required maxlength="150" help="Ví dụ: Siêu thị Xanh, Nhóm thiện nguyện Ánh Dương." />
<x-select name="type" label="Phân loại" :options="$types" :value="$sponsor->type?->value" required
          help="Nhà tài trợ: tài chính · Đồng hành: cùng tổ chức · Hiện vật: nguyên liệu, quà · Truyền thông: lan tỏa thông tin." />

<div class="image-picker mb-3" data-image-picker>
    <div class="image-picker-preview preview-tile sponsor-logo-preview" data-preview @if($logo) data-existing-image @endif>
        @if($logo)
            <img src="{{ $logo->url(480) }}" alt="Logo hiện tại" width="160" height="{{ $logo->heightFor(160) }}">
        @else
            <span class="image-picker-empty"><i class="bi bi-image" aria-hidden="true"></i> Chưa có logo</span>
        @endif
    </div>
    <div class="min-w-0">
        <label for="f_logo" class="form-label fw-semibold">Logo</label>
        <input type="file" id="f_logo" name="logo" accept="{{ $accept }}" data-image-input
               @class(['form-control', 'is-invalid' => $errors->has('logo')])
               aria-describedby="f_logo_help @error('logo') f_logo_error @enderror">
        <div id="f_logo_help" class="form-text">Không bắt buộc. Nên dùng PNG nền trong suốt, tối thiểu 400 px chiều ngang. Logo hiển thị nguyên tỉ lệ, không bị cắt.</div>
        @error('logo')<div id="f_logo_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @if($logo)
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="f_remove_logo">
                <label class="form-check-label" for="f_remove_logo">Xóa logo</label>
            </div>
        @endif
    </div>
</div>

{{-- Trình soạn thảo (như nội dung bài viết): HTML được lọc ở máy chủ khi lưu (Sponsor::description). --}}
<div class="mb-3">
    <label for="f_description" class="form-label fw-semibold">Giới thiệu</label>
    <textarea id="f_description" name="description" rows="6" data-rich-editor
              aria-describedby="f_description_help @error('description') f_description_error @enderror"
              @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $sponsor->description) }}</textarea>
    <div id="f_description_help" class="form-text">Hiện ở trang công khai của đơn vị. Dùng thanh công cụ để in đậm, danh sách, liên kết.
        Không ghi số điện thoại, email cá nhân hay số tiền tài trợ.</div>
    @error('description')<div id="f_description_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<x-input name="website" label="Website / trang mạng xã hội" :value="$sponsor->website" maxlength="255" inputmode="url" placeholder="https://…" />
<div class="form-check form-switch mb-3">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="f_is_active" @checked(old('is_active', $sponsor->is_active ?? true))>
    <label class="form-check-label" for="f_is_active">Hiển thị ở trang công khai</label>
</div>
