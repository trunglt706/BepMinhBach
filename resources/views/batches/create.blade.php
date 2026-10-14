@php use App\Enums\BatchStatus; @endphp
<x-form-shell title="Tạo đợt nấu" subtitle="Điền những gì đã biết; có thể sửa lại sau."
              :action="route('batches.store')" :back="route('batches.index')" back-label="Đợt nấu ăn" bare files>
    @include('batches._form')
    <div class="callout callout-info mb-3">
        <i class="bi bi-lightbulb" aria-hidden="true"></i>
        <span>Chọn <strong>Lưu nháp</strong> khi chưa chắc chắn; <strong>Lưu kế hoạch</strong> khi đã thống nhất thời gian và số suất.</span>
    </div>
    <x-slot:actions>
        <button type="submit" name="save_as" value="{{ BatchStatus::Planned->value }}" class="btn btn-primary">
            <i class="bi bi-calendar-check" aria-hidden="true"></i> Lưu kế hoạch
        </button>
        <button type="submit" name="save_as" value="{{ BatchStatus::Draft->value }}" class="btn btn-outline-primary">Lưu nháp</button>
    </x-slot:actions>
</x-form-shell>
