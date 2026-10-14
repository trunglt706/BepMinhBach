@php use App\Support\Format; @endphp
<x-layouts.public title="Bài viết" description="Tin hoạt động các đợt nấu ăn thiện nguyện và suất ăn cộng đồng của BếpMinhBạch."
                  :canonical="$canonical" :noindex="$noindex">
    <x-page-header title="Bài viết" subtitle="Tin hoạt động, nhìn lại các đợt nấu và lời cảm ơn từ đội bếp." />

    <form method="GET" action="{{ route('blog.index') }}" class="card card-body mb-3" role="search" aria-label="Tìm bài viết">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-5">
                <label for="b_q" class="form-label small mb-1">Từ khóa</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-body-secondary" aria-hidden="true"></i>
                    <input type="search" id="b_q" name="q" value="{{ $q }}" class="form-control ps-5" maxlength="100" placeholder="Ví dụ: cơm chay, cảm ơn">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <label for="b_tag" class="form-label small mb-1">Thẻ</label>
                <select id="b_tag" name="tag" class="form-select">
                    <option value="">Tất cả thẻ</option>
                    @foreach($tags as $t)
                        <option value="{{ $t->slug }}" @selected($tag?->is($t))>{{ $t->name }} ({{ $t->posts_count }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="b_dot" class="form-label small mb-1">Đợt nấu</label>
                <select id="b_dot" name="dot" class="form-select">
                    <option value="">Tất cả đợt</option>
                    @foreach($batches as $b)
                        <option value="{{ $b->id }}" @selected($batch?->is($b))>{{ $b->name }} · {{ $b->starts_at->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" data-loading-text="Đang tìm…">Tìm</button>
                @if($filtered)
                    <a href="{{ route('blog.index') }}" class="btn btn-outline-secondary btn-icon" aria-label="Xóa bộ lọc" title="Xóa bộ lọc"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
                @endif
            </div>
        </div>
    </form>

    @if($tags->isNotEmpty())
        <nav class="chip-row mb-3" aria-label="Thẻ phổ biến">
            @foreach($tags->take(12) as $t)
                @php($active = $tag?->is($t))
                <a href="{{ route('blog.index', array_filter(['q' => $q ?: null, 'dot' => $batch?->id, 'tag' => $active ? null : $t->slug])) }}"
                   @class(['chip', 'active' => $active]) @if($active) aria-current="true" @endif>#{{ $t->name }}</a>
            @endforeach
        </nav>
    @endif

    <p class="small text-body-secondary mb-3" role="status">
        @if($filtered)
            Tìm thấy <strong>{{ $posts->total() }}</strong> bài viết
            @if($q !== '') với từ khóa “{{ $q }}”@endif
            @if($tag) · thẻ <strong>#{{ $tag->name }}</strong>@endif
            @if($batch) · đợt <strong>{{ $batch->name }}</strong>@endif
        @else
            {{ $posts->total() }} bài viết
        @endif
    </p>

    @if($posts->isEmpty())
        <div class="card">
            <x-empty-state icon="bi-newspaper" :title="$filtered ? 'Không tìm thấy bài viết phù hợp' : 'Chưa có bài viết nào'">
                @if($filtered) Thử từ khóa khác hoặc bỏ bớt bộ lọc. @else Đội bếp sẽ sớm chia sẻ tin hoạt động tại đây. @endif
                @if($filtered)
                    <x-slot:action><a href="{{ route('blog.index') }}" class="btn btn-outline-primary">Xem tất cả bài viết</a></x-slot:action>
                @endif
            </x-empty-state>
        </div>
    @else
        <div class="row g-3" data-skeleton="cards">
            @foreach($posts as $post)
                <div class="col-md-6 col-xl-4">
                    <article class="card post-card h-100">
                        @if($post->cover)
                            {{-- Thẻ rộng tối đa ~430px: điện thoại tải bản 480/960, không tải bản 1600. --}}
                            <img class="post-card-cover" src="{{ $post->cover->url(480) }}" srcset="{{ $post->cover->srcset() }}"
                                 sizes="(min-width: 1200px) 400px, (min-width: 768px) 50vw, 100vw"
                                 width="480" height="{{ $post->cover->heightFor(480) }}" alt=""
                                 @if($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                        @endif
                        <div class="card-body d-flex flex-column">
                            <div class="meta-inline mb-2">
                                <span><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $post->published_at->toIso8601String() }}">{{ Format::date($post->published_at) }}</time></span>
                                @if($post->batch)
                                    <a href="{{ route('blog.index', ['dot' => $post->batch->id]) }}" class="post-batch-link"><i class="bi bi-egg-fried" aria-hidden="true"></i> {{ $post->batch->name }}</a>
                                @endif
                            </div>
                            <h2 class="h5 post-card-title"><a href="{{ route('blog.show', $post) }}">{{ $post->title }}</a></h2>
                            <p class="text-body-secondary mb-3">{{ $post->summary() }}</p>
                            @if($post->tags->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1 mt-auto">
                                    @foreach($post->tags as $t)
                                        <a href="{{ route('blog.index', ['tag' => $t->slug]) }}" class="tag-chip is-link">#{{ $t->name }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</x-layouts.public>
