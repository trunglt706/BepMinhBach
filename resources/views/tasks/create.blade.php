<x-form-shell title="Thêm công việc" :subtitle="$batch->name"
              :action="route('batches.tasks.store', $batch)"
              :back="route('batches.show', $batch).'#cong-viec'" back-label="Quay lại đợt nấu">
    @include('tasks._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu công việc</button>
        <button type="submit" name="add_another" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Lưu và thêm tiếp</button>
    </x-slot:actions>
</x-form-shell>
