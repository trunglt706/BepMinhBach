@php use App\Support\Format; @endphp
{{--
    Trang chương trình công khai: thông tin tổng hợp ở trên, bài viết về chương trình ở dưới (phân trang).
    Chỉ hiện tên, ngày, trạng thái, số suất — không hiện người điều phối, địa điểm, công việc, nguyên liệu, chi phí.
--}}
@php
    $cover = $batch->coverImage();
    $description = 'Chương trình “'.$batch->name.'” ngày '.Format::date($batch->starts_on).' — '.$batch->status->label().'. Bài viết và số suất do đội Góp Gió ghi nhận.';
@endphp
<x-layouts.public :title="$batch->name" :description="$description" :canonical="$canonical" :image="$cover?->url()">

    <nav aria-label="Đường dẫn" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Bài viết</a></li>
            <li class="breadcrumb-item active text-truncate" aria-current="page">{{ $batch->name }}</li>
        </ol>
    </nav>

    <header class="public-batch-hero mb-4">
        <div @class(['batch-slide-media', 'is-placeholder' => ! $cover])>
            @if($cover)
                <x-post-img :image="$cover" :width="$cover->largestWidth()" class="batch-slide-img" priority
                            sizes="(min-width: 1400px) 1320px, 100vw" />
            @endif
        </div>
        <div class="batch-slide-body">
            @if($batch->status === App\Enums\BatchStatus::InProgress)
                <span class="batch-live"><span class="batch-live-dot" aria-hidden="true"></span> Đang diễn ra</span>
            @else
                <x-status-badge :status="$batch->status" />
            @endif
            <h1 class="batch-slide-title">{{ $batch->name }}</h1>
            <span class="batch-slide-meta">
                <span><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $batch->starts_on->toDateString() }}">{{ Format::date($batch->starts_on) }}</time></span>
                <span><i class="bi bi-newspaper" aria-hidden="true"></i> {{ $posts->total() }} bài viết</span>
            </span>
        </div>
    </header>

    <div class="row g-4">
        <div class="col-lg-8 order-2 order-lg-1">
            <h2 class="h5 fw-bold mb-3"><i class="bi bi-newspaper" aria-hidden="true"></i> Bài viết về chương trình này</h2>
            @if($posts->isEmpty())
                <div class="card">
                    <x-empty-state icon="bi-newspaper" title="Chưa có bài viết về chương trình này">
                        Đội Góp Gió sẽ cập nhật tin của chương trình tại đây.
                        <x-slot:action><a href="{{ route('blog.index') }}" class="btn btn-outline-primary">Xem bài viết khác</a></x-slot:action>
                    </x-empty-state>
                </div>
            @else
                <div class="row g-3" data-skeleton="cards">
                    @foreach($posts as $post)
                        <div class="col-md-6">
                            @include('public.posts._card', ['post' => $post])
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $posts->links() }}</div>
            @endif
        </div>

        <aside class="col-lg-4 order-1 order-lg-2">
            <section class="card" aria-labelledby="batch-servings-title">
                <div class="panel-header"><h2 id="batch-servings-title" class="panel-title"><i class="bi bi-box-seam" aria-hidden="true"></i> Số suất</h2></div>
                <div class="card-body">
                    <x-chart.bars caption="Số suất của chương trình" :rows="[
                        ['label' => 'Dự kiến', 'value' => $batch->expected_servings, 'tone' => 'plan'],
                        ['label' => 'Đã chuẩn bị', 'value' => $batch->prepared_servings, 'tone' => 'primary'],
                        ['label' => 'Đã phân phát', 'value' => $batch->distributed_servings, 'tone' => 'primary'],
                    ]" />
                    <p class="small text-body-secondary mt-3 mb-0">Số liệu do đội Góp Gió tự ghi nhận, không phải kiểm toán độc lập.</p>
                </div>
            </section>

            @if($sponsorGroups->isNotEmpty())
                {{-- Đơn vị tham gia, nhóm theo phân loại; mỗi đơn vị dẫn tới trang riêng --}}
                <section class="card mt-3" aria-labelledby="batch-sponsors-title">
                    <div class="panel-header"><h2 id="batch-sponsors-title" class="panel-title"><i class="bi bi-award" aria-hidden="true"></i> Đơn vị tham gia</h2></div>
                    <div class="card-body pt-2">
                        @foreach(App\Enums\SponsorType::cases() as $type)
                            @continue(! $sponsorGroups->has($type->value))
                            <h3 class="small fw-bold text-body-secondary mt-2 mb-2"><i class="bi {{ $type->icon() }}" aria-hidden="true"></i> {{ $type->label() }}</h3>
                            <ul class="sponsor-chips list-unstyled mb-2">
                                @foreach($sponsorGroups[$type->value] as $s)
                                    <li>
                                        <a href="{{ route('sponsors.public.show', $s) }}" class="sponsor-chip">
                                            <x-sponsor-logo :sponsor="$s" size="sm" />
                                            <span class="min-w-0">
                                                <span class="d-block fw-semibold text-truncate">{{ $s->name }}</span>
                                                @if($s->pivot->contribution)<span class="d-block small text-body-secondary">{{ $s->pivot->contribution }}</span>@endif
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                        <a href="{{ route('sponsors.public.index') }}" class="small fw-semibold">Tất cả đơn vị đồng hành</a>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.public>
