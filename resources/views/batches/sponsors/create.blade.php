@php
    use App\Enums\SponsorType;
    $canCreate = auth()->user()->can('create', App\Models\Sponsor::class);
    // Mở sẵn phần "tạo mới" khi danh bạ trống hoặc vừa gửi tên đơn vị mới bị lỗi
    $newOpen = $options->isEmpty() || old('new_name') !== null || $errors->has('new_name') || $errors->has('new_type');
    $types = collect(SponsorType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
@endphp
<x-form-shell title="Thêm đơn vị tham gia" :subtitle="$batch->name"
              :action="route('batches.sponsors.store', $batch)" :back="route('batches.show', $batch).'#don-vi'" back-label="Quay lại chương trình">
    @if($options->isNotEmpty())
        <div class="mb-3">
            <label for="f_sponsor_id" class="form-label fw-semibold">Chọn đơn vị có sẵn</label>
            <select id="f_sponsor_id" name="sponsor_id" @class(['form-select', 'is-invalid' => $errors->has('sponsor_id')])
                    @error('sponsor_id') aria-invalid="true" aria-describedby="f_sponsor_id_error" @enderror>
                <option value="">— Chọn đơn vị —</option>
                @foreach($options->groupBy(fn ($s) => $s->type->label()) as $label => $items)
                    <optgroup label="{{ $label }}">
                        @foreach($items as $s)<option value="{{ $s->id }}" @selected((string) old('sponsor_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('sponsor_id')<div id="f_sponsor_id_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    @else
        @error('sponsor_id')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
    @endif

    @if($canCreate)
        {{-- Tạo nhanh đơn vị mới ngay trong form (logo, giới thiệu bổ sung sau ở trang "Đơn vị đồng hành") --}}
        <details class="quick-new mb-3" @if($newOpen) open @endif>
            <summary class="fw-semibold">{{ $options->isEmpty() ? 'Thêm đơn vị mới' : 'Hoặc thêm đơn vị mới chưa có trong danh sách' }}</summary>
            <div class="pt-2">
                <x-input name="new_name" label="Tên đơn vị" maxlength="150" help="Ví dụ: Siêu thị Xanh. Logo và giới thiệu bổ sung sau ở mục “Đơn vị đồng hành”." />
                <x-select name="new_type" label="Phân loại" :options="$types" :value="old('new_type', SponsorType::Sponsor->value)" />
            </div>
        </details>
    @elseif($options->isEmpty())
        <p class="mb-3">Chưa có đơn vị nào để thêm. Nhờ điều phối viên hoặc quản trị viên thêm đơn vị vào danh bạ.</p>
    @endif

    @if($options->isNotEmpty() || $canCreate)
        <x-input name="contribution" label="Đóng góp (không bắt buộc)" maxlength="200" help="Hiện công khai. Ví dụ: 500 phần quà; 200 kg gạo; hỗ trợ địa điểm." />
        <x-slot:actions>
            <button type="submit" class="btn btn-primary" data-loading-text="Đang lưu…"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm vào chương trình</button>
        </x-slot:actions>
    @endif
</x-form-shell>
