<x-form-shell title="Từ chối duyệt bài?" :subtitle="$post->title"
              :action="route('posts.reject.store', $post)"
              :back="route('posts.index', ['status' => 'pending'])" back-label="Bài viết" cancel-label="Không từ chối">
    <ul class="mb-3 ps-3">
        <li>Bài trở về <strong>bản nháp</strong>, không hiện ở trang công khai.</li>
        <li>Tác giả ({{ $post->author?->name ?? '—' }}) nhận thông báo kèm lý do, sửa rồi gửi duyệt lại.</li>
    </ul>
    <x-textarea name="review_note" label="Lý do / cần sửa gì" rows="3" maxlength="500" required
                help="Ví dụ: ảnh có mặt người nhận chưa được đồng ý; thiếu số suất đã phát." />
    <x-slot:actions>
        <button type="submit" class="btn btn-danger" data-loading-text="Đang gửi…"><i class="bi bi-x-circle" aria-hidden="true"></i> Từ chối</button>
    </x-slot:actions>
</x-form-shell>
