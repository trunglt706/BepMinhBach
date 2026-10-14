<section class="card mb-3" aria-labelledby="sec-info">
    <div class="panel-header"><h2 id="sec-info" class="panel-title"><span class="step-number">1</span> Thông tin đợt</h2></div>
    <div class="card-body pb-1">
        <x-input name="name" label="Tên đợt nấu" :value="$batch->name" required maxlength="150"
                 help="Ví dụ: Cơm chay chủ nhật 20/10" />
        <div class="row">
            <div class="col-md-6">
                <x-input name="starts_on" label="Ngày bắt đầu" type="date" required
                         :value="$batch->starts_on?->format('Y-m-d')" />
            </div>
            <div class="col-md-6">
                <x-input name="location_text" label="Địa điểm" :value="$batch->location_text" maxlength="255"
                         help="Ví dụ: Bếp chùa, sân sau. Không ghi địa chỉ người nhận." />
            </div>
        </div>
    </div>
</section>

<section class="card mb-3" aria-labelledby="sec-plan">
    <div class="panel-header"><h2 id="sec-plan" class="panel-title"><span class="step-number">2</span> Kế hoạch</h2></div>
    <div class="card-body pb-1">
        <div class="row">
            <div class="col-md-6">
                <x-input name="expected_servings" label="Số suất dự kiến" type="number" min="0" max="100000" step="1" inputmode="numeric"
                         :value="$batch->expected_servings" required />
            </div>
            <div class="col-md-6">
                <x-select name="coordinator_id" label="Người điều phối" required placeholder="— Chọn người điều phối —"
                          :options="$coordinators->mapWithKeys(fn ($u) => [$u->id => $u->name.($u->is_active ? '' : ' (đã khóa)')])->all()"
                          :value="$batch->coordinator_id" />
            </div>
        </div>
        <x-input name="estimated_cost_vnd" label="Chi phí dự kiến" money maxlength="17" :value="$batch->estimated_cost_vnd"
                 help="Không bắt buộc. Chỉ nhập số; dấu chấm phân cách hàng nghìn được thêm tự động." />
    </div>
</section>

<section class="card mb-3" aria-labelledby="sec-notes">
    <div class="panel-header"><h2 id="sec-notes" class="panel-title"><span class="step-number">3</span> Ghi chú</h2></div>
    <div class="card-body pb-1">
        <x-textarea name="notes" label="Ghi chú cho mọi người" :value="$batch->notes" rows="3" maxlength="2000"
                    help="Ví dụ: thực đơn, điểm tập trung, lưu ý chung." />
    </div>
</section>

@php
    $accept = implode(',', config('bmb.post_images.mimes'));
    $cover = $batch->coverImage();
@endphp
<section class="card mb-3" aria-labelledby="sec-cover">
    <div class="panel-header"><h2 id="sec-cover" class="panel-title"><span class="step-number">4</span> Ảnh nền</h2></div>
    <div class="card-body">
        <div class="image-picker" data-image-picker>
            <div class="image-picker-preview preview-tile" data-preview @if($cover) data-existing-image @endif>
                @if($cover)
                    <img src="{{ $cover->url(480) }}" alt="Ảnh nền hiện tại" width="240" height="{{ $cover->heightFor(240) }}">
                @else
                    <span class="image-picker-empty"><i class="bi bi-image" aria-hidden="true"></i> Chưa có ảnh</span>
                @endif
            </div>
            <div class="min-w-0">
                <label for="f_cover" class="form-label fw-semibold">Ảnh nền đợt nấu</label>
                <input type="file" id="f_cover" name="cover" accept="{{ $accept }}" data-image-input
                       @class(['form-control', 'is-invalid' => $errors->has('cover')])
                       aria-describedby="f_cover_help @error('cover') f_cover_error @enderror">
                <div id="f_cover_help" class="form-text">
                    Không bắt buộc. Hiện ở đầu trang đợt (quản lý và công khai) và trên slider trang chủ khi đợt đang diễn ra.<br>
                    <strong>Kích thước gợi ý: 1600 × 900 px</strong> (ảnh ngang, tỉ lệ 16:9), tối thiểu 1200 px chiều ngang; JPG/PNG/WebP, tối đa {{ (int) round(config('bmb.post_images.max_upload_kb') / 1024) }} MB.
                    Ảnh được cắt theo phần giữa để vừa khung — đặt chủ thể ở giữa ảnh. Tránh ảnh nhìn rõ mặt người nhận suất ăn.
                </div>
                @error('cover')<div id="f_cover_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if($cover)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="f_remove_cover">
                        <label class="form-check-label" for="f_remove_cover">Xóa ảnh nền</label>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
