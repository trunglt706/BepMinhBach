@php
    use App\Support\Format;
    $logo = $sponsor->logo();
    $description = $sponsor->description
        ? Illuminate\Support\Str::limit($sponsor->descriptionText(), 160)
        : $sponsor->type->label().' đồng hành cùng các chương trình thiện nguyện của Góp Gió.';
@endphp
{{-- Trang riêng của một đơn vị: phân loại, logo, tên, giới thiệu và các chương trình đã tham gia (chỉ chương trình công khai). --}}
<x-layouts.public :title="$sponsor->name" :description="$description" :canonical="route('sponsors.public.show', $sponsor)" :image="$logo?->url()">
    <nav aria-label="Đường dẫn" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('sponsors.public.index') }}">Đơn vị đồng hành</a></li>
            <li class="breadcrumb-item active text-truncate" aria-current="page">{{ $sponsor->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-4">
            <section class="card sponsor-profile">
                <div class="card-body text-center">
                    <x-sponsor-logo :sponsor="$sponsor" size="lg" class="mx-auto mb-3" />
                    <span class="status-pill status-planned mb-2"><i class="bi {{ $sponsor->type->icon() }}" aria-hidden="true"></i> {{ $sponsor->type->label() }}</span>
                    <h1 class="h4 fw-bold mt-2 mb-2">{{ $sponsor->name }}</h1>
                    @if($sponsor->website)
                        <a href="{{ $sponsor->website }}" target="_blank" rel="noopener noreferrer" class="small fw-semibold text-break">
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> {{ preg_replace('#^https?://(www\.)?#i', '', rtrim($sponsor->website, '/')) }}</a>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            @if($sponsor->description)
                <section class="card mb-3" aria-labelledby="about-title">
                    <div class="panel-header"><h2 id="about-title" class="panel-title"><i class="bi bi-info-circle" aria-hidden="true"></i> Giới thiệu</h2></div>
                    {{-- HTML đã lọc khi lưu (Sponsor::description → PostHtml::sanitize), in thẳng như nội dung bài viết --}}
                    <div class="card-body post-body">{!! $sponsor->description !!}</div>
                </section>
            @endif

            <section class="card" aria-labelledby="programs-title">
                <div class="panel-header"><h2 id="programs-title" class="panel-title"><i class="bi bi-calendar3" aria-hidden="true"></i> Chương trình đã tham gia <span class="panel-count">{{ $batches->count() }}</span></h2></div>
                @if($batches->isEmpty())
                    <div class="card-body small text-body-secondary">Chưa có chương trình công khai nào.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($batches as $b)
                            <li class="list-group-item py-3">
                                <a href="{{ route('blog.batch', $b) }}" class="d-flex gap-3 align-items-start text-reset text-decoration-none">
                                    <x-date-tile :date="$b->starts_on" />
                                    <span class="min-w-0">
                                        <span class="d-block fw-semibold">{{ $b->name }}</span>
                                        <x-status-badge :status="$b->status" class="my-1" />
                                        @if($b->pivot->contribution)<span class="d-block small text-body-secondary"><i class="bi bi-gift" aria-hidden="true"></i> {{ $b->pivot->contribution }}</span>@endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-layouts.public>
