<x-form-shell :title="'Sửa khoản chi “'.$expense->name.'”'" :subtitle="$batch->name"
              :action="route('expenses.update', $expense)" method="PUT"
              :back="route('batches.show', $batch).'#chi-phi'" back-label="Quay lại chương trình">
    @include('expenses._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
