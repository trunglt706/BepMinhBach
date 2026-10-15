<x-form-shell title="Thêm khoản chi" :subtitle="$batch->name"
              :action="route('batches.expenses.store', $batch)"
              :back="route('batches.show', $batch).'#chi-phi'" back-label="Quay lại chương trình">
    @include('expenses._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu khoản chi</button>
        <button type="submit" name="add_another" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Lưu và thêm tiếp</button>
    </x-slot:actions>
</x-form-shell>
