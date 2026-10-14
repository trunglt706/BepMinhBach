@php($target = $batch->status === App\Enums\BatchStatus::Completed ? App\Enums\BatchStatus::InProgress : App\Enums\BatchStatus::Draft)
<x-form-shell title="Mở lại đợt nấu?" :subtitle="$batch->name"
              :action="route('batches.reopen.store', $batch)"
              :back="route('batches.show', $batch)" back-label="Quay lại đợt nấu" cancel-label="Quay lại">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <x-status-badge :status="$batch->status" />
        <i class="bi bi-arrow-right text-body-secondary" aria-label="chuyển sang"></i>
        <x-status-badge :status="$target" />
    </div>
    <div class="callout callout-warning mb-3"><i class="bi bi-unlock" aria-hidden="true"></i>
        <span>Sau khi mở lại, số suất, chi phí, nguyên liệu và công việc có thể bị chỉnh sửa. Lý do mở lại sẽ được lưu trong lịch sử.</span></div>
    <x-textarea name="reason" label="Lý do mở lại" rows="3" maxlength="500" required
                help="Ví dụ: nhập sai số suất phân phát, cần sửa lại." />
    <div class="form-check mb-3">
        <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" id="confirm" value="1" required>
        <label class="form-check-label fw-semibold" for="confirm">Tôi xác nhận muốn mở lại đợt nấu này.</label>
        @error('confirm')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <x-slot:actions>
        <button type="submit" class="btn btn-warning" data-loading-text="Đang mở lại…"><i class="bi bi-unlock" aria-hidden="true"></i> Mở lại đợt nấu</button>
    </x-slot:actions>
</x-form-shell>
