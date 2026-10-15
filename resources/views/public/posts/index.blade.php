@php use App\Support\Format; @endphp
<x-layouts.public title="Bài viết" description="Tin hoạt động các chương trình thiện nguyện và suất ăn cộng đồng của Góp Gió."
                  :canonical="$canonical" :noindex="$noindex">
    @unless($noindex)
        <x-slot:head>
            {{-- Dữ liệu có cấu trúc cho trang chủ: tên site + ô tìm kiếm bài viết, tổ chức (không nêu tên cá nhân) --}}
            @php
                $siteJsonLd = [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        ['@type' => 'WebSite', 'name' => 'Góp Gió', 'url' => route('blog.index'), 'inLanguage' => 'vi',
                            'potentialAction' => ['@type' => 'SearchAction', 'target' => route('blog.index').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
                        ['@type' => 'Organization', 'name' => 'Góp Gió', 'url' => route('blog.index'), 'logo' => asset('favicon.svg')],
                    ],
                ];
            @endphp
            <script type="application/ld+json">@json($siteJsonLd)</script>
        </x-slot:head>
    @endunless
    @if($ongoing->isNotEmpty())
        @include('public.batches._slider', ['batches' => $ongoing])
    @endif
    {{-- Tiêu đề bên trái, tổng số bài (theo bộ lọc hiện tại) bên phải, cùng một hàng. --}}
    <header class="page-header home-header">
        <div class="home-header-row">
            <h1 class="page-title mb-0">Bài viết</h1>
            <span class="home-count" aria-label="{{ $posts->total() }} bài viết"><i class="bi bi-newspaper" aria-hidden="true"></i> {{ Format::number($posts->total(), '0') }} bài viết</span>
        </div>
        <p class="page-subtitle">Tin hoạt động, nhìn lại các chương trình và lời cảm ơn từ đội Góp Gió.</p>
    </header>

    @if($filtered)
        <p class="small text-body-secondary mb-3" role="status">
            Tìm thấy <strong>{{ $posts->total() }}</strong> bài viết
            @if($q !== '') với từ khóa “{{ $q }}”@endif
            @if($tag) · thẻ <strong>#{{ $tag->name }}</strong>@endif
            @if($batch) · chương trình <strong>{{ $batch->name }}</strong>@endif
            · <a href="{{ route('blog.index') }}">Xem tất cả</a>
        </p>
    @endif

    @if($posts->isEmpty())
        <div class="card">
            <x-empty-state icon="bi-newspaper" :title="$filtered ? 'Không tìm thấy bài viết phù hợp' : 'Chưa có bài viết nào'">
                @if($filtered) Thử từ khóa khác hoặc bỏ bớt bộ lọc. @else Đội Góp Gió sẽ sớm chia sẻ tin hoạt động tại đây. @endif
                @if($filtered)
                    <x-slot:action><a href="{{ route('blog.index') }}" class="btn btn-outline-primary">Xem tất cả bài viết</a></x-slot:action>
                @endif
            </x-empty-state>
        </div>
    @else
        <div class="row g-3" data-skeleton="cards">
            @foreach($posts as $post)
                <div class="col-md-6 col-xl-4">
                    @include('public.posts._card', ['post' => $post, 'priority' => $loop->first && $posts->onFirstPage() && $ongoing->isEmpty()])
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</x-layouts.public>
