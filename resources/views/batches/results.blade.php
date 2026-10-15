<x-form-shell title="Nhập số suất" :subtitle="$batch->name"
              :action="route('batches.results.update', $batch)" method="PUT"
              :back="route('batches.show', $batch).'#ket-qua'" back-label="Quay lại chương trình">
    <input type="hidden" name="results_version" value="{{ $batch->results_version }}">
    <div class="callout callout-muted mb-3">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>Nhập đúng số thực tế, kể cả khi khác dự kiến. Để trống nếu chưa biết — “chưa nhập” khác với 0.</span>
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="form-label">Số suất dự kiến</div>
            <div class="form-control form-control-lg bg-body-tertiary text-center tabular" aria-readonly="true">{{ App\Support\Format::number($batch->expected_servings) }}</div>
            <div class="form-text">Theo kế hoạch, không sửa ở đây.</div>
        </div>
        <div class="col-6 col-md-4">
            <x-input name="prepared_servings" label="Đã chuẩn bị" integer maxlength="7"
                     :value="$batch->prepared_servings" class="form-control-lg text-center tabular" help="Số nguyên, không âm." />
        </div>
        <div class="col-6 col-md-4">
            <x-input name="distributed_servings" label="Đã phân phát" integer maxlength="7"
                     :value="$batch->distributed_servings" class="form-control-lg text-center tabular" help="Không vượt quá số đã chuẩn bị." />
        </div>
    </div>
    <x-textarea name="variance_note" label="Giải thích chênh lệch (nếu có)" :value="$batch->variance_note" rows="3" maxlength="1000"
                help="Ví dụ: mưa nên ít người đến; còn 20 suất gửi chùa. Không ghi tên người nhận." />
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu số suất</button>
    </x-slot:actions>
</x-form-shell>
