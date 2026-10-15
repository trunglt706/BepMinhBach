<x-form-shell title="Sửa kênh thông báo" :subtitle="$batch->name"
              :action="route('channels.update', $channel)" method="PUT"
              :back="route('batches.show', $batch).'#thong-bao'" back-label="Quay lại chương trình">
    @include('channels._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
