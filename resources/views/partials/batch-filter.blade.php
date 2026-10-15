{{--
    Bộ lọc chương trình, giữ giá trị sau khi gửi.
    Biến: $filter, $action, $submitLabel, $statusMode ('chips' = chip phía trên, 'select' = ô chọn trong form)
--}}
@php
    $statusMode = $statusMode ?? 'select';
    $hasDateFilter = $filter->from || $filter->to;
    $keep = array_filter(['q' => $filter->q, 'from' => $filter->from?->format('Y-m-d'), 'to' => $filter->to?->format('Y-m-d')]);
@endphp

@if($statusMode === 'chips')
    <nav class="chip-row mb-3" aria-label="Lọc theo trạng thái">
        <a href="{{ $action.($keep ? '?'.http_build_query($keep) : '') }}" @class(['chip', 'active' => ! $filter->status]) @if(! $filter->status) aria-current="true" @endif>Tất cả</a>
        @foreach(App\Enums\BatchStatus::cases() as $status)
            <a href="{{ $action.'?'.http_build_query($keep + ['status' => $status->value]) }}"
               @class(['chip', 'active' => $filter->status === $status]) @if($filter->status === $status) aria-current="true" @endif>
                <i class="bi {{ $status->icon() }}" aria-hidden="true"></i> {{ $status->label() }}
            </a>
        @endforeach
    </nav>
@endif

<form method="GET" action="{{ $action }}" class="card card-body mb-3" role="search" aria-label="Lọc chương trình">
    @if($statusMode === 'chips' && $filter->status)
        <input type="hidden" name="status" value="{{ $filter->status->value }}">
    @endif
    <div class="row g-2 align-items-end">
        <div @class(['col-12', 'col-lg-5' => $statusMode === 'chips', 'col-lg-4' => $statusMode === 'select'])>
            <label for="filter_q" class="form-label small mb-1">Tìm theo tên</label>
            <div class="position-relative">
                <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-body-secondary" aria-hidden="true"></i>
                <input type="search" id="filter_q" name="q" value="{{ $filter->q }}" class="form-control ps-5" placeholder="Ví dụ: Bếp chủ nhật">
            </div>
        </div>
        @if($statusMode === 'select')
            <div class="col-12 col-md-4 col-lg-2">
                <label for="filter_status" class="form-label small mb-1">Trạng thái</label>
                <select id="filter_status" name="status" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach(App\Enums\BatchStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filter->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-6 col-md-4 col-lg-2 @unless($hasDateFilter || $statusMode === 'select') d-none d-md-block @endunless" data-date-field>
            <label for="filter_from" class="form-label small mb-1">Từ ngày</label>
            <input type="date" id="filter_from" name="from" value="{{ $filter->from?->format('Y-m-d') }}" class="form-control">
        </div>
        <div class="col-6 col-md-4 col-lg-2 @unless($hasDateFilter || $statusMode === 'select') d-none d-md-block @endunless" data-date-field>
            <label for="filter_to" class="form-label small mb-1">Đến ngày</label>
            <input type="date" id="filter_to" name="to" value="{{ $filter->to?->format('Y-m-d') }}" class="form-control">
        </div>
        <div @class(['col-12 d-flex gap-2', 'col-md-4 col-lg-3' => $statusMode === 'chips', 'col-lg-2' => $statusMode === 'select'])>
            <button type="submit" class="btn btn-primary flex-grow-1 text-nowrap" data-loading-text="Đang tìm…">{{ $submitLabel }}</button>
            @if($statusMode === 'chips' && ! $hasDateFilter)
                <button type="button" class="btn btn-outline-secondary d-md-none" data-show-dates aria-label="Lọc theo ngày">
                    <i class="bi bi-calendar-range" aria-hidden="true"></i> Ngày
                </button>
            @endif
        </div>
    </div>
    @unless($filter->isEmpty())
        <div class="mt-2 small d-flex flex-wrap gap-2 align-items-center">
            <span class="text-body-secondary"><i class="bi bi-funnel" aria-hidden="true"></i> Đang lọc: {{ $filter->describe() }}</span>
            <a href="{{ $action }}" class="fw-semibold">Xóa bộ lọc</a>
        </div>
    @endunless
</form>
