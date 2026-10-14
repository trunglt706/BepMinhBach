<x-form-shell title="Sửa đợt nấu" :subtitle="$batch->name"
              :action="route('batches.update', $batch)" method="PUT"
              :back="route('batches.show', $batch)" back-label="Quay lại đợt nấu" bare>
    @include('batches._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>

    {{-- Trang đầy đủ (không JS). Khi có JS, nút xóa nằm trong menu ⋯ của trang đợt nấu. --}}
    @can('delete', $batch)
        <x-slot:after>
            <section class="card border-danger-subtle mt-4" aria-labelledby="danger-title">
                <div class="card-body d-flex flex-column flex-sm-row gap-3 align-items-sm-center justify-content-between">
                    <div>
                        <h2 id="danger-title" class="h6 mb-1">Xóa đợt nháp</h2>
                        <p class="small text-body-secondary mb-0">Chỉ xóa được đợt nháp chưa có nguyên liệu, công việc, số suất hay chi phí.</p>
                    </div>
                    <form method="POST" action="{{ route('batches.destroy', $batch) }}"
                          data-confirm="Xóa hẳn đợt nấu nháp này? Thao tác không thể hoàn tác.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> Xóa đợt nháp</button>
                    </form>
                </div>
            </section>
        </x-slot:after>
    @endcan
</x-form-shell>
