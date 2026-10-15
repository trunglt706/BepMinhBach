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
        'author' => ['@type' => 'Organization', 'name' => 'Góp Gió'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Góp Gió', 'logo' => ['@type' => 'ImageObject', 'url' => asset('favicon.svg')]],
    ]);
@endphp
<x-layouts.public :title="$post->title" :description="$description" :canonical="$url" :noindex="$preview"
                   og-type="article" :image="$cover?->url()">
    <x-slot:head>
        @if($cover)
            <meta property="og:image:type" content="image/webp">
            <meta property="og:image:width" content="{{ $cover->largestWidth() }}">
            <meta property="og:image:height" content="{{ $cover->heightFor($cover->largestWidth()) }}">
            <meta property="og:image:alt" content="{{ $post->title }}">
        @endif
        @if($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">@endif
        <meta property="article:modified_time" content="{{ $post->updated_at->toAtomString() }}">
        @foreach($post->tags as $t)<meta property="article:tag" content="{{ $t->name }}">@endforeach
        @unless($preview)<script type="application/ld+json">@json($jsonLd)</script>@endunless
    </x-slot:head>

    <nav aria-label="Đường dẫn" class="mb-2">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Bài viết</a></li>
            @if($batch)<li class="breadcrumb-item"><a href="{{ route('blog.batch', $batch) }}">{{ $batch->name }}</a></li>@endif
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
                        @if($post->author)
                            <span class="post-author"><i class="bi bi-person" aria-hidden="true"></i> <span class="visually-hidden">Người viết:</span> {{ $post->author->name }}</span>
                        @endif
                        @if($post->published_at)
                            <span><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $post->published_at->toAtomString() }}">{{ Format::longDate($post->published_at) }}</time></span>
                        @endif
                        <span><i class="bi bi-clock" aria-hidden="true"></i> {{ $post->readingMinutes() }} phút đọc</span>
                        <span><i class="bi bi-eye" aria-hidden="true"></i> {{ Format::number($post->view_count, '0') }} lượt xem</span>
                    </div>
                    @if(! $preview && auth()->user()?->can('update', $post))
                        {{-- Người quản lý bài xem không làm tăng lượt xem (chống tăng ảo) — nói rõ để không tưởng là lỗi. --}}
                        <p class="small text-body-secondary mt-n3 mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i>
                            Bạn đang đăng nhập với quyền quản lý bài viết nên lượt xem của bạn không được tính. Mỗi người xem khác được tính 1 lần trong {{ intdiv((int) config('bmb.post_view_window_minutes', 360), 60) }} giờ.</p>
                    @endif
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
                                            <x-post-img :image="$image" :alt="$alt"
                                                        sizes="(min-width: 992px) 260px, (min-width: 576px) 33vw, 82vw" />
                                            <span class="album-zoom" aria-hidden="true"><i class="bi bi-zoom-in"></i></span>
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
                        <h2 id="batch-info-title" class="panel-title"><i class="bi bi-calendar3" aria-hidden="true"></i> Thuộc chương trình</h2>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-3">
                            <x-date-tile :date="$batch->starts_on" />
                            <div class="min-w-0">
                                <x-status-badge :status="$batch->status" class="mb-1" />
                                <a href="{{ route('blog.batch', $batch) }}" class="fw-bold d-block">{{ $batch->name }}</a>
                                <div class="small text-body-secondary"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ Format::date($batch->starts_on) }}</div>
                            </div>
                        </div>
                        <x-chart.bars caption="Số suất của chương trình" :rows="[
                            ['label' => 'Dự kiến', 'value' => $batch->expected_servings, 'tone' => 'plan'],
                            ['label' => 'Đã chuẩn bị', 'value' => $batch->prepared_servings, 'tone' => 'primary'],
                            ['label' => 'Đã phân phát', 'value' => $batch->distributed_servings, 'tone' => 'primary'],
                        ]" />
                        <p class="small text-body-secondary mt-3 mb-0">Số suất do đội Góp Gió tự ghi nhận.</p>
                    </div>
                    @if($batchPostCount > 1)
                        <div class="panel-footer">
                            <a href="{{ route('blog.batch', $batch) }}" class="fw-semibold">Xem {{ $batchPostCount }} bài viết về chương trình này <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
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
                                    <span class="related-thumb-wrap"><x-post-img :image="$r->cover" :width="72" class="related-thumb" sizes="72px" /></span>
                                @else
                                    <span class="related-thumb is-empty" aria-hidden="true"><i class="bi bi-wind"></i></span>
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
