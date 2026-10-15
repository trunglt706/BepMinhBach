<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Notification;

/**
 * Thông báo quy trình duyệt bài (bảng notifications, xem ở tab "Thông báo đã nhận"):
 * - requested: gửi cho quản trị viên khi có bài chờ duyệt;
 * - approved / rejected: gửi cho tác giả (kèm lý do khi từ chối).
 */
class PostReviewNotification extends Notification
{
    public const REQUESTED = 'requested';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @param  Post  $post  bài viết
     * @param  string  $event  requested | approved | rejected
     * @param  string|null  $by  tên người thực hiện
     */
    public function __construct(public Post $post, public string $event, public ?string $by = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = '“'.$this->post->title.'”';

        return match ($this->event) {
            self::REQUESTED => [
                'icon' => 'bi-hourglass-split',
                'title' => 'Bài chờ duyệt: '.$title,
                'body' => 'Gửi bởi '.($this->by ?? 'tác giả').'. Duyệt để đăng lên trang công khai hoặc từ chối kèm lý do.',
                'url' => route('posts.index', ['status' => 'pending']),
            ],
            self::APPROVED => [
                'icon' => 'bi-globe2',
                'title' => 'Bài đã được duyệt và đăng: '.$title,
                'body' => 'Duyệt bởi '.($this->by ?? 'quản trị viên').'.',
                'url' => route('blog.show', $this->post),
            ],
            default => [
                'icon' => 'bi-x-circle',
                'title' => 'Bài chưa được duyệt: '.$title,
                'body' => 'Lý do: '.($this->post->review_note ?: 'không ghi').'. Sửa bài rồi gửi duyệt lại.',
                'url' => route('posts.edit', $this->post),
            ],
        };
    }
}
