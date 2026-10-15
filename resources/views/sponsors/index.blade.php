@php
    use App\Enums\SponsorType;
    $filtered = $q !== '' || $type;
@endphp
<x-layouts.app title="Đơn vị đồng hành">
    <x-page-header title="Đơn vị đồng hành" subtitle="Nhà tài trợ, đơn vị đồng hành, hỗ trợ hiện vật, truyền thông. Gắn vào chương trình ở trang chi tiết chương trình.">
        <x-slot:actions>
            @can('create', App\Models\Sponsor::class)
                <a href="{{ route('sponsors.create') }}" data-modal class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm đơn vị</a>
            @endcan
            <x-filter-toggle target="sponsor-filter" :active="$filtered" />
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('sponsors.index') }}" id="sponsor-filter" class="card card-body mb-3" role="search" aria-label="Lọc đơn vị">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <label for="s_q" class="form-label small mb-1">Tìm theo tên</label>
                <input type="search" id="s_q" name="q" value="{{ $q }}" class="form-control" maxlength="100">
            </div>
            <div class="col-7 col-md-4">
                <label for="s_type" class="form-label small mb-1">Phân loại</label>
                <select id="s_type" name="type" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach(SponsorType::cases() as $t)
                        <option value="{{ $t->value }}" @selected($type === $t)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-5 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary" data-loading-text="Đang tìm…">Lọc</button>
            </div>
        </div>
    </form>

    <div id="sponsors-list" data-region>
    @if($sponsors->isEmpty())
        <div class="card">
            <x-empty-state icon="bi-award" :title="$filtered ? 'Không tìm thấy đơn vị phù hợp' : 'Chưa có đơn vị nào'">
                @unless($filtered) Thêm nhà tài trợ, đơn vị đồng hành để ghi nhận và cảm ơn họ trên trang công khai. @endunless
            </x-empty-state>
        </div>
    @else
        <div class="card">
            <div class="panel-header"><h2 class="panel-title"><i class="bi bi-award" aria-hidden="true"></i> Đơn vị <span class="panel-count">{{ $sponsors->total() }}</span></h2></div>
            <ul class="list-group list-group-flush" data-skeleton="list">
                @foreach($sponsors as $s)
                    <li @class(['list-group-item py-3 d-flex gap-3 align-items-center', 'bg-body-tertiary' => ! $s->is_active])>
                        <x-sponsor-logo :sponsor="$s" size="sm" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $s->name }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1 small">
                                <span class="status-pill status-draft"><i class="bi {{ $s->type->icon() }}" aria-hidden="true"></i> {{ $s->type->label() }}</span>
                                @unless($s->is_active)<span class="status-pill status-cancelled"><i class="bi bi-eye-slash" aria-hidden="true"></i> Đang ẩn</span>@endunless
                                <span class="text-body-secondary align-self-center">{{ $s->batches_count }} chương trình</span>
                            </div>
                        </div>
                        @can('update', $s)
                            <x-row-menu :label="$s->name" :edit-url="route('sponsors.edit', $s)"
                                        :delete-url="auth()->user()->can('delete', $s) ? route('sponsors.destroy', $s) : null"
                                        :confirm="'Xóa đơn vị “'.$s->name.'”? Đơn vị sẽ bị gỡ khỏi '.$s->batches_count.' chương trình đã tham gia.'">
                                @if($s->is_active)
                                    <li><a class="dropdown-item" href="{{ route('sponsors.public.show', $s) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Xem trang công khai</a></li>
                                @endif
                            </x-row-menu>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $sponsors->links() }}</div>
    @endif
    </div>
</x-layouts.app>
