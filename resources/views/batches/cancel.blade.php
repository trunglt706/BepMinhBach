<x-form-shell title="Hủy chương trình?" :subtitle="$batch->name"
              :action="route('batches.cancel.store', $batch)"
              :back="route('batches.show', $batch)" back-label="Quay lại chương trình" cancel-label="Không hủy">
    <h3 class="h6 mb-2"><i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i> Điều gì sẽ xảy ra</h3>
    <ul class="mb-3 ps-3">
        <li>Chương trình chuyển sang “Đã hủy”, <strong>dữ liệu không bị xóa</strong>.</li>
        <li>Không ai cập nhật được nguyên liệu, công việc, số suất hay chi phí của chương trình này.</li>
        @if($openTasks > 0)
            <li>{{ $openTasks }} công việc chưa xong sẽ được giữ nguyên và bị khóa thao tác.</li>
        @endif
        <li>Điều phối viên có thể mở lại chương trình sau này (chương trình sẽ quay về trạng thái nháp).</li>
    </ul>
    <x-textarea name="cancellation_reason" label="Lý do hủy" rows="3" maxlength="1000" help="Không bắt buộc nhưng nên ghi để mọi người hiểu." />
    <div class="form-check mb-3">
        <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" id="confirm" value="1" required data-confirm-gate>
        <label class="form-check-label fw-semibold" for="confirm">Tôi xác nhận muốn hủy chương trình này.</label>
        @error('confirm')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <x-slot:actions>
        <button type="submit" class="btn btn-danger" data-loading-text="Đang hủy…"><i class="bi bi-x-circle" aria-hidden="true"></i> Hủy chương trình</button>
    </x-slot:actions>
</x-form-shell>
