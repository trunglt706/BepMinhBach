{{-- Danh sách đơn vị tài trợ / hỗ trợ, nhóm theo phân loại. --}}
<x-layouts.public title="Đơn vị đồng hành"
                  description="Nhà tài trợ, đơn vị đồng hành, hỗ trợ hiện vật và truyền thông đã cùng Góp Gió thực hiện các chương trình thiện nguyện."
                  :canonical="route('sponsors.public.index')">
    <x-page-header title="Đơn vị đồng hành" subtitle="Cảm ơn những người bạn đã cùng Góp Gió mang các chương trình đến với cộng đồng." />

    @if($groups->isEmpty())
        <div class="card">
            <x-empty-state icon="bi-award" title="Chưa có đơn vị nào">
                Bạn muốn đồng hành cùng các chương trình? <a href="{{ route('contact.create', ['chu-de' => 'partnership']) }}">Gửi lời nhắn cho chúng tôi</a>.
            </x-empty-state>
        </div>
    @else
        @foreach($types as $type)
            @continue(! $groups->has($type->value))
            <section class="mb-4" aria-labelledby="type-{{ $type->value }}">
                <h2 id="type-{{ $type->value }}" class="h5 fw-bold mb-1"><i class="bi {{ $type->icon() }}" aria-hidden="true"></i> {{ $type->label() }}</h2>
                <p class="small text-body-secondary mb-3">{{ $type->description() }}</p>
                <div class="row g-3">
                    @foreach($groups[$type->value] as $s)
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="{{ route('sponsors.public.show', $s) }}" class="card sponsor-card h-100">
                                <x-sponsor-logo :sponsor="$s" size="lg" />
                                <span class="sponsor-card-body">
                                    <span class="fw-semibold d-block">{{ $s->name }}</span>
                                    <span class="small text-body-secondary">{{ $s->batches_count }} chương trình</span>
                                </span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        <div class="callout callout-muted"><i class="bi bi-heart" aria-hidden="true"></i>
            <span>Muốn cùng đồng hành? <a href="{{ route('contact.create') }}">Gửi lời nhắn cho đội Góp Gió</a>.</span></div>
    @endif
</x-layouts.public>
