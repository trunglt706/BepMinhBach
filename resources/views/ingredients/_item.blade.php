{{-- Một dòng nguyên liệu. Biến: $ingredient (đã gán relation batch). --}}
@php
    use App\Support\Format;
    $isThisOld = (string) old('_ingredient') === (string) $ingredient->id;
    $canReport = ! $ingredient->needs_restock && auth()->user()->can('reportShortage', $ingredient);
    $canResolve = $ingredient->needs_restock && auth()->user()->can('resolveShortage', $ingredient);
@endphp
<div @class(['item-row', 'is-short' => $ingredient->needs_restock])>
    <div class="item-body">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="min-w-0">
                <p class="item-title">{{ $ingredient->name }}</p>
                @if($ingredient->needs_restock)
                    <span class="status-pill task-blocked mt-1"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Cần bổ sung</span>
                @endif
            </div>
            <div class="d-flex align-items-start gap-1">
                <div class="qty">
                    <div class="qty-main" data-expected>{{ Format::quantity($ingredient->expected_quantity) }} {{ $ingredient->unit }}</div>
                    <div class="qty-sub">dự kiến</div>
                </div>
                @can('update', $ingredient)
                    <x-row-menu :label="$ingredient->name" :edit-url="route('ingredients.edit', $ingredient)"
                                :delete-url="route('ingredients.destroy', $ingredient)" :confirm="'Xóa nguyên liệu “'.$ingredient->name.'”?'" />
                @endcan
            </div>
        </div>

        <div class="small mt-1">
            Thực tế đã dùng:
            @if($ingredient->actual_quantity !== null)
                <strong>{{ Format::quantity($ingredient->actual_quantity) }} {{ $ingredient->unit }}</strong>
            @else
                <span class="text-body-secondary">chưa ghi nhận</span>
            @endif
        </div>
        @if($ingredient->notes)<div class="small text-body-secondary text-pre-line">{{ $ingredient->notes }}</div>@endif
        @if($ingredient->needs_restock)
            <div class="callout callout-danger mt-2">
                <i class="bi bi-megaphone" aria-hidden="true"></i>
                <span>Báo thiếu bởi {{ $ingredient->restockReporter?->name ?? '—' }} lúc {{ Format::dateTime($ingredient->restock_reported_at) }}@if($ingredient->restock_note): {{ $ingredient->restock_note }}@endif</span>
            </div>
        @endif

        @if($canReport || $canResolve)
            <div class="item-actions">
                @if($canReport)
                    <details class="quick-action" @if($isThisOld && $errors->has('restock_note')) open @endif>
                        <summary class="btn btn-outline-danger btn-sm"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Báo thiếu</summary>
                        <form method="POST" action="{{ route('ingredients.shortage', $ingredient) }}" data-ajax class="quick-form mt-2">
                            @csrf
                            <input type="hidden" name="_ingredient" value="{{ $ingredient->id }}">
                            <label for="restock_note_{{ $ingredient->id }}" class="form-label small">Ghi chú ngắn (không bắt buộc)</label>
                            <input type="text" id="restock_note_{{ $ingredient->id }}" name="restock_note" maxlength="500"
                                   value="{{ $isThisOld ? old('restock_note') : '' }}"
                                   class="form-control @if($isThisOld && $errors->has('restock_note')) is-invalid @endif" placeholder="Ví dụ: còn thiếu khoảng 2 kg">
                            @if($isThisOld)
                                @error('restock_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @endif
                            <button type="submit" class="btn btn-danger btn-sm mt-2" data-loading-text="Đang gửi…">Gửi báo thiếu</button>
                        </form>
                    </details>
                @endif
                @if($canResolve)
                    <form method="POST" action="{{ route('ingredients.shortage.resolve', $ingredient) }}" data-ajax>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-primary btn-sm" data-loading-text="Đang cập nhật…"><i class="bi bi-check2" aria-hidden="true"></i> Đã đủ, gỡ cảnh báo</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
