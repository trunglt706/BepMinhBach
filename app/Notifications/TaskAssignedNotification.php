<?php

namespace App\Notifications;

use App\Models\Task;
use App\Support\Format;
use Illuminate\Notifications\Notification;

/**
 * Ghi nhận cho người được giao việc (bảng notifications) — hiện ở tab "Thông báo đã nhận" của tài khoản.
 * Chỉ lưu tên việc, tên chương trình, hạn và link; không lưu ghi chú tự do.
 */
class TaskAssignedNotification extends Notification
{
    /**
     * @param  Task  $task  công việc vừa được giao
     * @param  string|null  $by  tên người giao
     */
    public function __construct(public Task $task, public ?string $by = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Dữ liệu lưu vào cột data.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $batch = $this->task->batch;

        return [
            'icon' => 'bi-person-check',
            'title' => 'Được giao việc “'.$this->task->title.'”',
            'body' => 'Chương trình “'.$batch->name.'” ('.Format::date($batch->starts_on).')'
                .($this->task->due_at ? ' · hạn '.$this->task->due_at->format('H:i d/m/Y') : '')
                .($this->by ? ' · giao bởi '.$this->by : ''),
            'url' => route('batches.show', $batch).'#cong-viec',
        ];
    }
}
