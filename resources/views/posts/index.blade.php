@php
    use App\Enums\PostStatus;
    use App\Support\Format;
    $filtered = $q !== '' || $status;
@endphp
<x-layouts.app title="Bài viết">
    <x-page-header title="Bài viết" subtitle="Tin hoạt động cho mọi người xem trên trang công khai. Bản nháp chỉ người quản lý thấy.">
        <x-slot:actions>
            <a href="{{ route('posts.create') }}" data-modal class="btn btn-primary"><i class="bi bi-pencil-square" aria-hidden="true"></i> Viết bài</a>
            <a href="{{ route('blog.index') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Trang công khai</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('posts.index') }}" class="card card-body mb-3" role="search" aria-label="Lọc bài viết">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <label for="p_q" class="form-label small mb-1">Tìm theo tiêu đề</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-body-secondary" aria-hidden="true"></i>
                    <input type="search" id="p_q" name="q" value="{{ $q }}" class="form-control ps-5" maxlength="100">
                </div>
            </div>
            <div class="col-7 col-md-4">
                <label for="p_status" class="form-label small mb-1">Trạng thái</label>
                <select id="p_status" name="status" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach(PostStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-5 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary" data-loading-text="Đang tìm…">Lọc</button>
            </div>
        </div>
    </form>

    <div id="posts-list" data-region>
    @if($posts->isEmpty())
        <div class="card">
            <x-empty-state icon="bi-newspaper" :title="$filtered ? 'Không tìm thấy bài viết phù hợp' : 'Chưa có bài viết nào'">
                @unless($filtered) Kể lại các đợt nấu, cảm ơn tình nguyện viên hoặc thông báo đợt sắp tới. @endunless
            </x-empty-state>
        </div>
    @else
        <div class="card">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-newspaper" aria-hidden="true"></i> Bài viết <span class="panel-count">{{ $posts->total() }}</span></h2>
            </div>
            <ul class="list-group list-group-flush" data-skeleton="list" data-skeleton-leading="tile">
                @foreach($posts as $post)
                    @php($isPublished = $post->status === PostStatus::Published)
                    <li class="list-group-item py-3 d-flex gap-3 align-items-start">
                        @if($post->cover)
                            <img src="{{ $post->cover->url(480) }}" alt="" width="64" height="{{ $post->cover->heightFor(64) }}" class="related-thumb d-none d-sm-block" loading="lazy">
                        @else
                            <span class="related-thumb is-empty d-none d-sm-grid" aria-hidden="true"><i class="bi bi-image"></i></span>
                        @endif
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="{{ $post->status->badgeClass() }}"><i class="bi {{ $post->status->icon() }}" aria-hidden="true"></i> {{ $post->status->label() }}</span>
                                <a href="{{ route('blog.show', $post) }}" class="fw-semibold" target="_blank" rel="noopener">{{ $post->title }}</a>
                            </div>
                            <div class="meta-inline">
                                @if($post->published_at)<span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ Format::dateTime($post->published_at) }}</span>@endif
                                @if($post->batch)<span><i class="bi bi-egg-fried" aria-hidden="true"></i> {{ $post->batch->name }}</span>@endif
                                <span><i class="bi bi-person" aria-hidden="true"></i> {{ $post->author?->name }}</span>
                                <span><i class="bi bi-eye" aria-hidden="true"></i> {{ Format::number($post->view_count, '0') }} lượt xem</span>
                            </div>
                            @if($post->tags->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @foreach($post->tags as $t)<span class="tag-chip">#{{ $t->name }}</span>@endforeach
                                </div>
                            @endif
                        </div>
                        <div class="d-flex gap-2 flex-shrink-0 align-items-center">
                            @can('publish', $post)
                                <form method="POST" action="{{ route('posts.publish', $post) }}" data-ajax
                                      @if($isPublished) data-confirm="Gỡ bài “{{ $post->title }}” khỏi trang công khai? Khách sẽ không xem được nữa." data-confirm-label="Gỡ bài" @endif>
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $isPublished ? 'btn-outline-secondary' : 'btn-outline-primary' }}"
                                            data-loading-text="{{ $isPublished ? 'Đang gỡ…' : 'Đang đăng…' }}">
                                        <i class="bi {{ $isPublished ? 'bi-eye-slash' : 'bi-globe2' }}" aria-hidden="true"></i> {{ $isPublished ? 'Gỡ bài' : 'Đăng' }}
                                    </button>
                                </form>
                            @endcan
                            <x-row-menu :label="$post->title" :edit-url="route('posts.edit', $post)"
                                        :delete-url="route('posts.destroy', $post)" :confirm="'Xóa hẳn bài “'.$post->title.'”? Thao tác không thể hoàn tác.'" />
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
    </div>
</x-layouts.app>
