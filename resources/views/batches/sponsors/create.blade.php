<x-form-shell title="Thêm đơn vị tham gia" :subtitle="$batch->name"
              :action="route('batches.sponsors.store', $batch)" :back="route('batches.show', $batch).'#don-vi'" back-label="Quay lại chương trình">
    @if($options->isEmpty())
        <p class="mb-2">Không còn đơn vị nào để thêm (mọi đơn vị đang hiển thị đã có trong chương trình).</p>
    @else
        @php
            $grouped = $options->groupBy(fn ($s) => $s->type->label());
        @endphp
        <div class="mb-3">
            <label for="f_sponsor_id" class="form-label fw-semibold">Đơn vị <span class="required-mark" aria-hidden="true">*</span></label>
            <select id="f_sponsor_id" name="sponsor_id" required @class(['form-select', 'is-invalid' => $errors->has('sponsor_id')])
                    @error('sponsor_id') aria-invalid="true" aria-describedby="f_sponsor_id_error" @enderror>
                <option value="">— Chọn đơn vị —</option>
                @foreach($grouped as $label => $items)
                    <optgroup label="{{ $label }}">
                        @foreach($items as $s)<option value="{{ $s->id }}" @selected((string) old('sponsor_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('sponsor_id')<div id="f_sponsor_id_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <x-input name="contribution" label="Đóng góp (không bắt buộc)" maxlength="200" help="Hiện công khai. Ví dụ: 500 phần quà; 200 kg gạo; hỗ trợ địa điểm." />
    @endif
    @can('create', App\Models\Sponsor::class)
        <p class="small text-body-secondary mb-0">Chưa có trong danh sách? <a href="{{ route('sponsors.create') }}" target="_blank" rel="noopener">Thêm đơn vị mới</a> rồi mở lại form này.</p>
    @endcan
    @if($options->isNotEmpty())
        <x-slot:actions>
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm vào chương trình</button>
        </x-slot:actions>
    @endif
</x-form-shell>
