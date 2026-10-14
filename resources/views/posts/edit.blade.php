<x-form-shell title="Sửa bài viết" :subtitle="$post->title"
              :action="route('posts.update', $post)" method="PUT"
              :back="route('posts.index')" back-label="Bài viết" bare files>
    @if($post->published_at)
        <x-slot:before>
            <div class="callout callout-muted mb-3"><i class="bi bi-link-45deg" aria-hidden="true"></i>
                <span>Bài đã từng đăng nên đường dẫn được giữ nguyên khi đổi tiêu đề.</span></div>
        </x-slot:before>
    @endif
    @include('posts._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
