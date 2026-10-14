@php use App\Enums\PostStatus; @endphp
<x-form-shell title="Viết bài mới" subtitle="Bài viết đã đăng ai cũng xem được, không cần đăng nhập."
              :action="route('posts.store')" :back="route('posts.index')" back-label="Bài viết" bare files>
    @include('posts._form')
    <x-slot:actions>
        <button type="submit" name="save_as" value="{{ PostStatus::Published->value }}" class="btn btn-primary" data-loading-text="Đang đăng…">
            <i class="bi bi-globe2" aria-hidden="true"></i> Đăng bài
        </button>
        <button type="submit" name="save_as" value="{{ PostStatus::Draft->value }}" class="btn btn-outline-primary">Lưu nháp</button>
    </x-slot:actions>
</x-form-shell>
