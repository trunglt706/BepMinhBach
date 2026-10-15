<x-form-shell title="Thêm nguyên liệu" :subtitle="$batch->name"
              :action="route('batches.ingredients.store', $batch)"
              :back="route('batches.show', $batch).'#nguyen-lieu'" back-label="Quay lại chương trình">
    @include('ingredients._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu nguyên liệu</button>
        <button type="submit" name="add_another" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Lưu và thêm tiếp</button>
    </x-slot:actions>
</x-form-shell>
