<x-form-shell title="Hoàn tất đợt nấu" :subtitle="$batch->name"
              :action="route('batches.complete.store', $batch)"
              :back="route('batches.show', $batch)" back-label="Quay lại đợt nấu" cancel-label="Quay lại" :warn="false">
    <x-slot:before>
        <h3 class="h6 mb-2"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Kiểm tra trước khi hoàn tất</h3>
        @if($reminders)
            <ul class="list-unstyled mb-2">
                @foreach($reminders as $reminder)
                    <li class="callout callout-warning mb-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ $reminder }}</span></li>
                @endforeach
            </ul>
            <p class="small text-body-secondary">Bạn vẫn có thể hoàn tất. Hệ thống không tự điền số liệu còn thiếu.</p>
        @else
            <div class="callout callout-success mb-3"><i class="bi bi-check2-all" aria-hidden="true"></i><span>Đã có đủ công việc, số suất và chi phí.</span></div>
        @endif
    </x-slot:before>

    <div class="callout callout-info mb-3"><i class="bi bi-lock" aria-hidden="true"></i>
        <span>Sau khi hoàn tất, đợt nấu sẽ được <strong>khóa</strong>: không thêm/sửa nguyên liệu, công việc, số suất hay chi phí.
        Điều phối viên có thể mở lại nếu cần (có ghi lý do).</span></div>
    <x-textarea name="completion_notes" label="Ghi chú rút kinh nghiệm" rows="3" maxlength="2000"
                help="Không bắt buộc. Ví dụ: lần sau cần thêm người chia suất." />
    <div class="form-check mb-3">
        <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" id="confirm" value="1" required>
        <label class="form-check-label fw-semibold" for="confirm">Tôi đã xem lại và xác nhận hoàn tất đợt nấu này.</label>
        @error('confirm')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <x-slot:actions>
        <button type="submit" class="btn btn-primary" data-loading-text="Đang hoàn tất…"><i class="bi bi-check-circle" aria-hidden="true"></i> Xác nhận hoàn tất</button>
    </x-slot:actions>
</x-form-shell>
