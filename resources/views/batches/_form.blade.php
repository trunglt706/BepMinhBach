<section class="card mb-3" aria-labelledby="sec-info">
    <div class="panel-header"><h2 id="sec-info" class="panel-title"><span class="step-number">1</span> Thông tin đợt</h2></div>
    <div class="card-body pb-1">
        <x-input name="name" label="Tên đợt nấu" :value="$batch->name" required maxlength="150"
                 help="Ví dụ: Cơm chay chủ nhật 20/10" />
        <div class="row">
            <div class="col-md-6">
                <x-input name="starts_at" label="Ngày giờ bắt đầu" type="datetime-local" required
                         :value="$batch->starts_at?->format('Y-m-d\TH:i')" />
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
