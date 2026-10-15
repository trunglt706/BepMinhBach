<x-form-shell :title="'Sửa công việc “'.$task->title.'”'" :subtitle="$batch->name"
              :action="route('tasks.update', $task)" method="PUT"
              :back="route('batches.show', $batch).'#cong-viec'" back-label="Quay lại chương trình">
    <x-slot:before>
        <div class="callout callout-muted mb-3">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>Trạng thái hiện tại: <x-task-status :status="$task->status" /> — cập nhật trạng thái bằng các nút ở danh sách công việc.</span>
        </div>
    </x-slot:before>
    @include('tasks._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
