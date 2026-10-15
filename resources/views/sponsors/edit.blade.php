<x-form-shell :title="'Sửa đơn vị “'.$sponsor->name.'”'" :subtitle="'Trang công khai: '.route('sponsors.public.show', $sponsor)"
              :action="route('sponsors.update', $sponsor)" method="PUT" :back="route('sponsors.index')" back-label="Đơn vị đồng hành" files>
    @include('sponsors._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
