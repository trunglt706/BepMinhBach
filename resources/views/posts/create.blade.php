@php
    use App\Enums\PostStatus;
    $isAdmin = auth()->user()->isAdmin();
@endphp
<x-form-shell title="Viết bài mới"
              :subtitle="$isAdmin ? 'Bài đã đăng ai cũng xem được, không cần đăng nhập.' : 'Bài hiện ở trang công khai sau khi quản trị viên duyệt.'"
              :action="route('posts.store')" :back="route('posts.index')" back-label="Bài viết" bare files>
    @include('posts._form')
    <x-slot:actions>
        @if($isAdmin)
            <button type="submit" name="save_as" value="{{ PostStatus::Published->value }}" class="btn btn-primary" data-loading-text="Đang đăng…">
                <i class="bi bi-globe2" aria-hidden="true"></i> Đăng bài
            </button>
        @else
            <button type="submit" name="save_as" value="{{ PostStatus::Pending->value }}" class="btn btn-primary" data-loading-text="Đang gửi…">
                <i class="bi bi-send" aria-hidden="true"></i> Gửi duyệt
            </button>
        @endif
        <button type="submit" name="save_as" value="{{ PostStatus::Draft->value }}" class="btn btn-outline-primary">Lưu nháp</button>
    </x-slot:actions>
</x-form-shell>
