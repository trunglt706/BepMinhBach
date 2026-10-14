<x-form-shell title="Thêm kênh thông báo" :subtitle="$batch->name"
              :action="route('batches.channels.store', $batch)"
              :back="route('batches.show', $batch).'#thong-bao'" back-label="Quay lại đợt nấu">
    @if(empty($drivers))
        <p class="mb-0">Đợt này đã có đủ các loại kênh (Slack, Telegram, Chatwork). Sửa kênh có sẵn trên trang đợt nấu.</p>
    @else
        @include('channels._form')
    @endif
    <x-slot:actions>
        @unless(empty($drivers))
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu kênh</button>
        @endunless
    </x-slot:actions>
</x-form-shell>
