@php
    use App\Enums\PostStatus;
    use App\Support\Format;
    $filtered = $q !== '' || $status;
    $me = auth()->user();
@endphp
<x-layouts.app title="Bài viết">
    <x-page-header title="Bài viết" :subtitle="$me->isAdmin() ? 'Bài của mọi người. Bài chờ duyệt nằm đầu danh sách — duyệt để đăng lên trang công khai.' : 'Bài của bạn. Gửi duyệt để quản trị viên đăng lên trang công khai.'">
        <x-slot:actions>
            <a href="{{ route('posts.create') }}" data-modal class="btn btn-primary"><i class="bi bi-pencil-square" aria-hidden="true"></i> Viết bài</a>
            <a href="{{ route('blog.index') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Trang công khai</a>
            <x-filter-toggle target="post-filter" :active="$filtered" />
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('posts.index') }}" id="post-filter" class="card card-body mb-3" role="search" aria-label="Lọc bài viết">
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
                @unless($filtered) Kể lại các chương trình, cảm ơn tình nguyện viên hoặc thông báo chương trình sắp tới. @endunless
            </x-empty-state>
        </div>
    @else
        <div class="card">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-newspaper" aria-hidden="true"></i> Bài viết <span class="panel-count">{{ $posts->total() }}</span></h2>
            </div>
            <ul class="list-group list-group-flush" data-skeleton="list" data-skeleton-leading="tile">
                @foreach($posts as $post)
                    <li class="list-group-item py-3 d-flex gap-3 align-items-start">
                        @if($post->cover)
                            <span class="related-thumb-wrap d-none d-sm-block"><x-post-img :image="$post->cover" :width="64" class="related-thumb" sizes="64px" /></span>
                        @else
                            <span class="related-thumb is-empty d-none d-sm-grid" aria-hidden="true"><i class="bi bi-image"></i></span>
                        @endif
                        <div class="flex-grow-1 min-w-0">
                            {{-- Dòng trên: trạng thái + thao tác; tiêu đề nằm riêng một dòng, chiếm trọn chiều ngang --}}
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="{{ $post->status->badgeClass() }}"><i class="bi {{ $post->status->icon() }}" aria-hidden="true"></i> {{ $post->status->label() }}</span>
                                @php
                                    // Thao tác theo quy trình duyệt, theo thứ tự ưu tiên; thao tác đầu là nút chính.
                                    $actions = array_values(array_filter([
                                        $me->can('publish', $post) ? ['url' => route('posts.publish', $post), 'icon' => 'bi-check2-circle', 'style' => 'btn-primary',
                                            'label' => $post->status === PostStatus::Pending ? 'Duyệt & đăng' : 'Đăng bài', 'loading' => 'Đang đăng…'] : null,
                                        $me->can('submit', $post) ? ['url' => route('posts.submit', $post), 'icon' => 'bi-send', 'style' => 'btn-outline-primary',
                                            'label' => 'Gửi duyệt', 'loading' => 'Đang gửi…'] : null,
                                        $me->can('reject', $post) ? ['url' => route('posts.reject', $post), 'icon' => 'bi-x-circle', 'style' => 'btn-outline-danger',
                                            'label' => 'Từ chối', 'modal' => true] : null,
                                        $me->can('withdraw', $post) ? ['url' => route('posts.withdraw', $post), 'icon' => 'bi-arrow-counterclockwise', 'style' => 'btn-outline-secondary',
                                            'label' => 'Rút yêu cầu duyệt', 'loading' => 'Đang rút…'] : null,
                                        $me->can('unpublish', $post) ? ['url' => route('posts.unpublish', $post), 'icon' => 'bi-eye-slash', 'style' => 'btn-outline-secondary',
                                            'label' => 'Gỡ bài', 'loading' => 'Đang gỡ…',
                                            'confirm' => 'Gỡ bài “'.$post->title.'” khỏi trang công khai? Khách sẽ không xem được nữa.'] : null,
                                    ]));
                                @endphp
                                <div class="d-flex gap-2 align-items-center ms-auto">
                                    {{-- Máy tính: thao tác chính để ngoài; điện thoại: mọi thao tác nằm trong menu "⋮" --}}
                                    @if($primary = $actions[0] ?? null)
                                        @if($primary['modal'] ?? false)
                                            <a href="{{ $primary['url'] }}" data-modal class="btn btn-sm {{ $primary['style'] }} d-none d-sm-inline-flex">
                                                <i class="bi {{ $primary['icon'] }}" aria-hidden="true"></i> {{ $primary['label'] }}</a>
                                        @else
                                            <form method="POST" action="{{ $primary['url'] }}" data-ajax class="d-none d-sm-block"
                                                  @isset($primary['confirm']) data-confirm="{{ $primary['confirm'] }}" data-confirm-label="{{ $primary['label'] }}" @endisset>
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $primary['style'] }}" data-loading-text="{{ $primary['loading'] }}">
                                                    <i class="bi {{ $primary['icon'] }}" aria-hidden="true"></i> {{ $primary['label'] }}
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    @can('update', $post)
                                        <x-row-menu :label="$post->title" :edit-url="route('posts.edit', $post)"
                                                    :delete-url="$me->can('delete', $post) ? route('posts.destroy', $post) : null"
                                                    :confirm="'Xóa hẳn bài “'.$post->title.'”? Thao tác không thể hoàn tác.'">
                                            @foreach($actions as $i => $a)
                                                <li @class(['d-sm-none' => $i === 0])>
                                                    @if($a['modal'] ?? false)
                                                        <a href="{{ $a['url'] }}" data-modal class="dropdown-item"><i class="bi {{ $a['icon'] }}" aria-hidden="true"></i> {{ $a['label'] }}</a>
                                                    @else
                                                        <form method="POST" action="{{ $a['url'] }}" data-ajax
                                                              @isset($a['confirm']) data-confirm="{{ $a['confirm'] }}" data-confirm-label="{{ $a['label'] }}" @endisset>
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="bi {{ $a['icon'] }}" aria-hidden="true"></i> {{ $a['label'] }}</button>
                                                        </form>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </x-row-menu>
                                    @endcan
                                </div>
                            </div>
                            <a href="{{ route('blog.show', $post) }}" class="d-block fw-semibold mb-1" target="_blank" rel="noopener">{{ $post->title }}</a>
                            @if($post->status === PostStatus::Draft && $post->review_note)
                                <div class="small text-danger mb-1"><i class="bi bi-x-circle" aria-hidden="true"></i> Chưa được duyệt: {{ $post->review_note }}</div>
                            @endif
                            <div class="meta-inline">
                                @if($post->published_at)<span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ Format::dateTime($post->published_at) }}</span>@endif
                                @if($post->batch)<span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ $post->batch->name }}</span>@endif
                                {{-- Người viết + lượt xem luôn cùng một dòng --}}
                                <span class="meta-pair">
                                    <span><i class="bi bi-person" aria-hidden="true"></i> {{ $post->author?->name }}</span>
                                    <span><i class="bi bi-eye" aria-hidden="true"></i> {{ Format::number($post->view_count, '0') }} lượt xem</span>
                                </span>
                            </div>
                            @if($post->tags->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @foreach($post->tags as $t)<span class="tag-chip">#{{ $t->name }}</span>@endforeach
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
    </div>
</x-layouts.app>
