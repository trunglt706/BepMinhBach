@php
    use App\Support\Format;
    $batch = $post->batch;
    $cover = $post->cover;
    $url = route('blog.show', $post);
    $description = $post->summary(160);
    // Dữ liệu có cấu trúc (schema.org) cho công cụ tìm kiếm. Tác giả là tổ chức, không nêu tên cá nhân.
    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $description,
        'url' => $url,
        'mainEntityOfPage' => $url,
        'datePublished' => $post->published_at?->toAtomString(),
        'dateModified' => $post->updated_at?->toAtomString(),
        'image' => $cover ? [$cover->url()] : null,
        'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
        'author' => ['@type' => 'Organization', 'name' => 'BếpMinhBạch'],
        'publisher' => ['@type' => 'Organization', 'name' => 'BếpMinhBạch', 'logo' => ['@type' => 'ImageObject', 'url' => asset('favicon.svg')]],
    ]);
@endphp
<x-layouts.public :title="$post->title" :description="$description" :canonical="$url" :noindex="$preview">
    <x-slot:head>
        <meta property="og:type" content="article">
        <meta property="og:title" content="{{ $post->title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $url }}">
        @if($cover)
            <meta property="og:image" content="{{ $cover->url() }}">
            <meta property="og:image:type" content="image/webp">
            <meta property="og:image:width" content="{{ $cover->largestWidth() }}">
            <meta property="og:image:height" content="{{ $cover->heightFor($cover->largestWidth()) }}">
            <meta property="og:image:alt" content="{{ $post->title }}">
        @endif
        @if($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">@endif
        <meta property="article:modified_time" content="{{ $post->updated_at->toAtomString() }}">
        @foreach($post->tags as $t)<meta property="article:tag" content="{{ $t->name }}">@endforeach
        <meta name="twitter:card" content="{{ $cover ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $post->title }}">
        <meta name="twitter:description" content="{{ $description }}">
        @if($cover)<meta name="twitter:image" content="{{ $cover->url() }}">@endif
        @unless($preview)<script type="application/ld+json">@json($jsonLd)</script>@endunless
    </x-slot:head>

    <nav aria-label="Đường dẫn" class="mb-2">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Bài viết</a></li>
            @if($batch)<li class="breadcrumb-item"><a href="{{ route('blog.index', ['dot' => $batch->id]) }}">{{ $batch->name }}</a></li>@endif
            <li class="breadcrumb-item active text-truncate" aria-current="page">{{ $post->title }}</li>
        </ol>
    </nav>

    @if($preview)
        <div class="alert alert-warning" role="status">
            <i class="bi bi-eye" aria-hidden="true"></i>
            <div><strong>Xem trước bản nháp.</strong> Khách chưa xem được bài này. Đăng bài trong mục Bài viết của trang quản lý.</div>
        </div>
    @endif

    <div class="row g-4">
        <article class="col-lg-8" aria-labelledby="post-title">
            <div class="card overflow-hidden">
                @if($cover)
                    {{-- Ảnh đầu bài: tải ngay (ưu tiên cao), trình duyệt chọn bản vừa màn hình qua srcset. --}}
                    <img class="post-cover" src="{{ $cover->url(960) }}" srcset="{{ $cover->srcset() }}"
                         sizes="(min-width: 1400px) 860px, (min-width: 992px) 66vw, 100vw"
                         width="{{ $cover->largestWidth() }}" height="{{ $cover->heightFor($cover->largestWidth()) }}"
                         alt="{{ $post->title }}" fetchpriority="high" decoding="async">
                @endif
                <div class="card-body p-4 p-md-5">
                    @if($post->tags->isNotEmpty())
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            @foreach($post->tags as $t)
                                <a href="{{ route('blog.index', ['tag' => $t->slug]) }}" class="tag-chip" rel="tag">#{{ $t->name }}</a>
                            @endforeach
                        </div>
                    @endif
                    <h1 id="post-title" class="post-title">{{ $post->title }}</h1>
                    <div class="meta-inline mb-4">
                        @if($post->published_at)
                            <span><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $post->published_at->toAtomString() }}">{{ Format::longDate($post->published_at) }}</time></span>
                        @endif
                        <span><i class="bi bi-clock" aria-hidden="true"></i> {{ $post->readingMinutes() }} phút đọc</span>
                        <span><i class="bi bi-eye" aria-hidden="true"></i> {{ Format::number($post->view_count, '0') }} lượt xem</span>
                    </div>
                    @if($post->excerpt)
                        <p class="post-lead">{{ $post->excerpt }}</p>
                    @endif
                    {{-- HTML đã được lọc theo danh sách cho phép khi lưu (App\Support\PostHtml) nên in trực tiếp an toàn. --}}
                    <div class="post-body">{!! $post->body !!}</div>

                    @if($post->gallery->isNotEmpty())
                        <section class="mt-5" aria-labelledby="album-title">
                            <h2 id="album-title" class="h5 fw-bold mb-3"><i class="bi bi-images" aria-hidden="true"></i> Album ảnh <span class="panel-count">{{ $post->gallery->count() }}</span></h2>
                            {{-- Bấm ảnh để xem lớn, vuốt qua lại (PhotoSwipe). Không có JS: mở ảnh lớn ở tab mới. --}}
                            <div class="album-grid" data-gallery>
                                @foreach($post->gallery as $image)
                                    @php($alt = $image->caption ?: 'Ảnh '.$loop->iteration.' – '.$post->title)
                                    <figure class="album-item">
                                        <a href="{{ $image->url() }}" target="_blank" rel="noopener"
                                           data-pswp-width="{{ $image->largestWidth() }}" data-pswp-height="{{ $image->heightFor($image->largestWidth()) }}"
                                           data-pswp-srcset="{{ $image->srcset() }}"
                                           aria-label="Xem ảnh lớn: {{ $alt }}">
                                            <img src="{{ $image->url(480) }}" srcset="{{ $image->srcset() }}"
                                                 sizes="(min-width: 992px) 260px, (min-width: 576px) 33vw, 50vw"
                                                 width="480" height="{{ $image->heightFor(480) }}" alt="{{ $alt }}" loading="lazy" decoding="async">
                                        </a>
                                        @if($image->caption)<figcaption>{{ $image->caption }}</figcaption>@endif
                                    </figure>
                                @endforeach
                            </div>
                            @if($post->gallery->count() > 1)
                                {{-- Chỉ hiện trên điện thoại (album trượt ngang). --}}
                                <p class="album-swipe-hint mb-0" aria-hidden="true"><i class="bi bi-arrow-left-right"></i> Vuốt ngang để xem {{ $post->gallery->count() }} ảnh, chạm để phóng to</p>
                            @endif
                        </section>
                    @endif
                </div>
            </div>
        </article>

        <aside class="col-lg-4 d-flex flex-column gap-3" aria-label="Thông tin thêm">
            @if($batch)
                <section class="card" aria-labelledby="batch-info-title">
                    <div class="panel-header">
                        <h2 id="batch-info-title" class="panel-title"><i class="bi bi-egg-fried" aria-hidden="true"></i> Thuộc đợt nấu</h2>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-3">
                            <x-date-tile :date="$batch->starts_at" />
                            <div class="min-w-0">
                                <x-status-badge :status="$batch->status" class="mb-1" />
                                <div class="fw-bold">{{ $batch->name }}</div>
                                <div class="small text-body-secondary"><i class="bi bi-clock" aria-hidden="true"></i> {{ Format::dateTime($batch->starts_at) }}</div>
                            </div>
                        </div>
                        <x-chart.bars caption="Số suất của đợt nấu" empty="Chưa ghi nhận" :rows="[
                            ['label' => 'Dự kiến', 'value' => $batch->expected_servings, 'tone' => 'plan'],
                            ['label' => 'Đã chuẩn bị', 'value' => $batch->prepared_servings, 'tone' => 'primary'],
                            ['label' => 'Đã phân phát', 'value' => $batch->distributed_servings, 'tone' => 'primary'],
                        ]" />
                        <p class="small text-body-secondary mt-3 mb-0">Số suất do đội bếp tự ghi nhận.</p>
                    </div>
                    @if($batchPostCount > 1)
                        <div class="panel-footer">
                            <a href="{{ route('blog.index', ['dot' => $batch->id]) }}" class="fw-semibold">Xem {{ $batchPostCount }} bài viết về đợt này <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    @endif
                </section>
            @endif

            @if($related->isNotEmpty())
                <section class="card" aria-labelledby="related-title">
                    <div class="panel-header"><h2 id="related-title" class="panel-title"><i class="bi bi-newspaper" aria-hidden="true"></i> Bài viết khác</h2></div>
                    <ul class="list-group list-group-flush">
                        @foreach($related as $r)
                            <li class="list-group-item py-3 d-flex gap-3 align-items-center">
                                @if($r->cover)
                                    <img src="{{ $r->cover->url(480) }}" alt="" width="72" height="{{ $r->cover->heightFor(72) }}" class="related-thumb" loading="lazy" decoding="async">
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('blog.show', $r) }}" class="fw-semibold d-block">{{ $r->title }}</a>
                                    <span class="small text-body-secondary">{{ Format::date($r->published_at) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.public>
