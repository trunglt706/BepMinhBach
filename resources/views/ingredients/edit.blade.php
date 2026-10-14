<x-form-shell :title="'Sửa nguyên liệu “'.$ingredient->name.'”'" :subtitle="$batch->name"
              :action="route('ingredients.update', $ingredient)" method="PUT"
              :back="route('batches.show', $batch).'#nguyen-lieu'" back-label="Quay lại đợt nấu">
    @include('ingredients._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
