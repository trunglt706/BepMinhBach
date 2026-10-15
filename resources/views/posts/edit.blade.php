@php
    use App\Enums\PostStatus;
    $isAdmin = auth()->user()->isAdmin();
@endphp
<x-form-shell title="Sửa bài viết" :subtitle="$post->title"
              :action="route('posts.update', $post)" method="PUT"
              :back="route('posts.index')" back-label="Bài viết" bare files>
    <x-slot:before>
        @if($post->status === PostStatus::Draft && $post->review_note)
            <div class="callout callout-danger mb-3" role="note"><i class="bi bi-x-circle" aria-hidden="true"></i>
                <span><strong>Chưa được duyệt:</strong> {{ $post->review_note }} — sửa bài rồi bấm “Gửi duyệt” ở danh sách bài viết.</span></div>
        @elseif($post->status === PostStatus::Pending)
            <div class="callout callout-muted mb-3" role="note"><i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <span>Bài đang chờ quản trị viên duyệt. Bạn vẫn sửa được; bài giữ trạng thái chờ duyệt.</span></div>
        @elseif($post->status === PostStatus::Published && ! $isAdmin)
            <div class="callout callout-warning mb-3" role="note"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <span>Bài đang đăng. Khi lưu thay đổi, bài <strong>tạm ẩn khỏi trang công khai</strong> và chờ quản trị viên duyệt lại.</span></div>
        @endif
        @if($post->published_at)
            <div class="callout callout-muted mb-3"><i class="bi bi-link-45deg" aria-hidden="true"></i>
                <span>Bài đã từng đăng nên đường dẫn được giữ nguyên khi đổi tiêu đề.</span></div>
        @endif
    </x-slot:before>
    @include('posts._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
