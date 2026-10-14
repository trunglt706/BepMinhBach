@php use App\Support\Format; @endphp
{{-- Thẻ bài viết (trang chủ, trang đợt nấu). $priority: ảnh tải ngay — chỉ cho ảnh lớn đầu tiên của màn hình đầu. --}}
<article class="card post-card h-100">
    {{-- Thẻ rộng tối đa ~430px: điện thoại tải bản 480/960, không tải bản 1600.
         Chỉ thẻ đầu tiên tải ngay (ảnh lớn nhất màn hình đầu); các thẻ khác tải khi cuộn tới. --}}
    <div @class(['post-card-media', 'is-placeholder' => ! $post->cover])>
        @if($post->cover)
            <x-post-img :image="$post->cover" class="post-card-cover" :priority="$priority ?? false"
                        sizes="(min-width: 1200px) 400px, (min-width: 768px) 50vw, 100vw" />
        @else
            <span class="post-card-placeholder" aria-hidden="true"><i class="bi bi-egg-fried"></i></span>
        @endif
        @if($post->batch)
            {{-- Badge đợt nấu nổi trên ảnh (kiểu "tin nóng"); bấm riêng được (nằm trên lớp liên kết phủ cả thẻ). --}}
            <a href="{{ route('blog.batch', $post->batch) }}" class="post-card-badge" title="Đợt nấu: {{ $post->batch->name }}">
                <i class="bi bi-fire" aria-hidden="true"></i><span class="visually-hidden">Đợt nấu:</span>
                <span class="text-truncate">{{ $post->batch->name }}</span>
            </a>
        @endif
    </div>
    <div class="card-body d-flex flex-column">
        {{-- Ngày đăng bên trái, tác giả bên phải (canh đều hai bên). --}}
        <div class="post-card-meta mb-2">
            <span><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $post->published_at->toIso8601String() }}">{{ Format::date($post->published_at) }}</time></span>
            @if($post->author)
                <span class="post-card-author text-truncate"><i class="bi bi-person" aria-hidden="true"></i><span class="visually-hidden">Tác giả:</span> {{ $post->author->name }}</span>
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
