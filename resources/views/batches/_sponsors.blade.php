@php
    use App\Enums\SponsorType;
    $canManage = auth()->user()->can('manageSponsors', $batch);
    $groups = $batch->sponsors->groupBy(fn ($s) => $s->type->value);
@endphp
{{-- Đơn vị tài trợ / hỗ trợ của chương trình, nhóm theo phân loại (cũng hiện ở trang chương trình công khai). --}}
<section id="don-vi" class="card scroll-mt" aria-labelledby="h-don-vi">
    <div class="panel-header">
        <h2 id="h-don-vi" class="panel-title"><i class="bi bi-award" aria-hidden="true"></i> Đơn vị tham gia <span class="panel-count">{{ $batch->sponsors->count() }}</span></h2>
        @if($canManage)
            <a href="{{ route('batches.sponsors.create', $batch) }}" data-modal class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm</a>
        @endif
    </div>
    @if($batch->sponsors->isEmpty())
        <div class="card-body small text-body-secondary">Chưa có nhà tài trợ hay đơn vị đồng hành nào.</div>
    @else
        <div class="card-body pt-2">
            @foreach(SponsorType::cases() as $type)
                @continue(! $groups->has($type->value))
                <h3 class="small fw-bold text-body-secondary mt-2 mb-1"><i class="bi {{ $type->icon() }}" aria-hidden="true"></i> {{ $type->label() }}</h3>
                <ul class="list-unstyled mb-0">
                    @foreach($groups[$type->value] as $s)
                        <li class="d-flex align-items-center gap-2 py-1">
                            <x-sponsor-logo :sponsor="$s" size="sm" />
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold text-truncate">{{ $s->name }} @unless($s->is_active)<span class="small text-body-secondary fw-normal">(đang ẩn)</span>@endunless</div>
                                @if($s->pivot->contribution)<div class="small text-body-secondary">{{ $s->pivot->contribution }}</div>@endif
                            </div>
                            @if($canManage)
                                <form method="POST" action="{{ route('batches.sponsors.destroy', [$batch, $s->id]) }}" data-ajax
                                      data-confirm="Gỡ “{{ $s->name }}” khỏi chương trình này?" data-confirm-label="Gỡ">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-link text-body-secondary" aria-label="Gỡ {{ $s->name }} khỏi chương trình"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    @endif
</section>
