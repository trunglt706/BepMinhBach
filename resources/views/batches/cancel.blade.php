<x-form-shell title="Hủy đợt nấu?" :subtitle="$batch->name"
              :action="route('batches.cancel.store', $batch)"
              :back="route('batches.show', $batch)" back-label="Quay lại đợt nấu" cancel-label="Không hủy" :warn="false">
    <h3 class="h6 mb-2"><i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i> Điều gì sẽ xảy ra</h3>
    <ul class="mb-3 ps-3">
        <li>Đợt nấu chuyển sang “Đã hủy”, <strong>dữ liệu không bị xóa</strong>.</li>
        <li>Không ai cập nhật được nguyên liệu, công việc, số suất hay chi phí của đợt này.</li>
        @if($openTasks > 0)
            <li>{{ $openTasks }} công việc chưa xong sẽ được giữ nguyên và bị khóa thao tác.</li>
        @endif
        <li>Điều phối viên có thể mở lại đợt sau này (đợt sẽ quay về trạng thái nháp).</li>
    </ul>
    <x-textarea name="cancellation_reason" label="Lý do hủy" rows="3" maxlength="1000" help="Không bắt buộc nhưng nên ghi để mọi người hiểu." />
    <div class="form-check mb-3">
        <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" id="confirm" value="1" required>
        <label class="form-check-label fw-semibold" for="confirm">Tôi xác nhận muốn hủy đợt nấu này.</label>
        @error('confirm')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <x-slot:actions>
        <button type="submit" class="btn btn-danger" data-loading-text="Đang hủy…"><i class="bi bi-x-circle" aria-hidden="true"></i> Hủy đợt nấu</button>
    </x-slot:actions>
</x-form-shell>
