<?php

namespace Tests;

use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cho người ghi nhận / tình nguyện viên vào phạm vi xem của chương trình (CookingBatch::scopeVisibleTo) bằng một việc
     * đã xong được giao cho họ. Việc ở trạng thái "Hoàn thành" để ít ảnh hưởng tới số liệu "việc chưa xong".
     *
     * @param  CookingBatch  $batch  chương trình
     * @param  User  ...$users  người được giao
     */
    protected function assignToBatch(CookingBatch $batch, User ...$users): void
    {
        foreach ($users as $user) {
            Task::factory()->for($batch, 'batch')->create([
                'title' => 'Việc phân công #'.$user->id,
                'assignee_id' => $user->id,
                'status' => TaskStatus::Done,
                'completed_at' => now(),
            ]);
        }
    }
}
