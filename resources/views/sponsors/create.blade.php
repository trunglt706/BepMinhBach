<x-form-shell title="Thêm đơn vị tài trợ / hỗ trợ" subtitle="Thông tin hiện ở trang công khai “Đơn vị đồng hành”."
              :action="route('sponsors.store')" :back="route('sponsors.index')" back-label="Đơn vị đồng hành" files>
    @include('sponsors._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu đơn vị</button>
    </x-slot:actions>
</x-form-shell>
