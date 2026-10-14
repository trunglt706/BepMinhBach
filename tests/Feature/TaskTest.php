<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    /** T15 */
    public function test_coordinator_can_create_and_assign_task(): void
    {
        $batch = CookingBatch::factory()->create();
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($batch->coordinator)->post("/batches/{$batch->id}/tasks", [
            'title' => 'Sơ chế rau',
            'assignee_id' => $volunteer->id,
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('batches.show', $batch).'#cong-viec');

        $task = Task::firstOrFail();
        $this->assertSame($volunteer->id, $task->assignee_id);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame($batch->coordinator_id, $task->created_by);
    }

    public function test_task_cannot_be_assigned_to_locked_or_missing_user(): void
    {
        $batch = CookingBatch::factory()->create();
        $locked = User::factory()->locked()->create();

        $this->actingAs($batch->coordinator)->post("/batches/{$batch->id}/tasks", ['title' => 'A', 'assignee_id' => $locked->id])
            ->assertSessionHasErrors('assignee_id');
        $this->actingAs($batch->coordinator)->post("/batches/{$batch->id}/tasks", ['title' => '', 'assignee_id' => 99999, 'due_at' => 'abc'])
            ->assertSessionHasErrors(['title', 'assignee_id', 'due_at']);
    }

    public function test_coordinator_can_reassign_task(): void
    {
        $batch = CookingBatch::factory()->create();
        $task = Task::factory()->for($batch, 'batch')->create();
        $newAssignee = User::factory()->volunteer()->create();

        $this->actingAs($batch->coordinator)->put("/tasks/{$task->id}", ['title' => $task->title, 'assignee_id' => $newAssignee->id])
            ->assertRedirect();
        $this->assertSame($newAssignee->id, $task->fresh()->assignee_id);
    }

    /** T16 + T17 */
    public function test_assignee_can_update_status_and_completion_is_recorded(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $volunteer = User::factory()->volunteer()->create();
        $task = Task::factory()->for($batch, 'batch')->create(['assignee_id' => $volunteer->id]);

        $this->actingAs($volunteer)->post("/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);

        $this->travelTo(now()->setTime(10, 15));
        $this->actingAs($volunteer)->post("/tasks/{$task->id}/status", ['status' => 'done'])->assertSessionHas('success');

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame($volunteer->id, $task->completed_by);
        $this->assertSame(now()->format('Y-m-d H:i'), $task->completed_at->format('Y-m-d H:i'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'task.status', 'user_id' => $volunteer->id]);

        // Mở lại việc thì xóa thông tin hoàn thành
        $this->actingAs($volunteer)->post("/tasks/{$task->id}/status", ['status' => 'todo']);
        $this->assertNull($task->fresh()->completed_at);
    }

    /** T18 */
    public function test_blocked_requires_reason(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $volunteer = User::factory()->volunteer()->create();
        $task = Task::factory()->for($batch, 'batch')->create(['assignee_id' => $volunteer->id]);

        $this->actingAs($volunteer)->post("/tasks/{$task->id}/status", ['status' => 'blocked', 'blocked_note' => ''])
            ->assertSessionHasErrors(['blocked_note' => 'Vui lòng ghi ngắn gọn lý do đang bị vướng.']);
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);

        $this->actingAs($volunteer)->post("/tasks/{$task->id}/status", ['status' => 'blocked', 'blocked_note' => 'Hết gas']);
        $task->refresh();
        $this->assertSame(TaskStatus::Blocked, $task->status);
        $this->assertSame('Hết gas', $task->blocked_note);

        $this->actingAs($batch->coordinator)->get('/')->assertSee('Việc bị vướng')->assertSee('Hết gas');
    }

    public function test_invalid_status_is_rejected(): void
    {
        $batch = CookingBatch::factory()->create();
        $task = Task::factory()->for($batch, 'batch')->create();

        $this->actingAs($batch->coordinator)->post("/tasks/{$task->id}/status", ['status' => 'xong-roi'])
            ->assertSessionHasErrors('status');
    }

    /** T04 — IDOR: không cập nhật được việc của người khác bằng cách đổi ID. */
    public function test_volunteer_cannot_update_task_assigned_to_someone_else(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $owner = User::factory()->volunteer()->create();
        $intruder = User::factory()->volunteer()->create();
        $task = Task::factory()->for($batch, 'batch')->create(['assignee_id' => $owner->id]);
        $unassigned = Task::factory()->for($batch, 'batch')->create(['assignee_id' => null]);

        $this->actingAs($intruder)->post("/tasks/{$task->id}/status", ['status' => 'done'])->assertForbidden();
        $this->actingAs($intruder)->post("/tasks/{$unassigned->id}/status", ['status' => 'done'])->assertForbidden();
        $this->actingAs($intruder)->get("/tasks/{$task->id}/edit")->assertForbidden();
        $this->actingAs($intruder)->put("/tasks/{$task->id}", ['title' => 'Đổi tên', 'assignee_id' => $intruder->id])->assertForbidden();
        $this->actingAs($intruder)->delete("/tasks/{$task->id}")->assertForbidden();
        // Người được giao cũng không tự sửa/phân công lại
        $this->actingAs($owner)->put("/tasks/{$task->id}", ['title' => 'Đổi tên'])->assertForbidden();

        $task->refresh();
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame($owner->id, $task->assignee_id);
    }

    public function test_coordinator_can_update_any_task_status_and_delete(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $task = Task::factory()->for($batch, 'batch')->create(['assignee_id' => User::factory()->volunteer()]);
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->post("/tasks/{$task->id}/status", ['status' => 'done'])->assertRedirect();
        $this->assertSame($coordinator->id, $task->fresh()->completed_by);

        $this->actingAs($coordinator)->delete("/tasks/{$task->id}")->assertRedirect();
        $this->assertModelMissing($task);
    }

    public function test_my_tasks_shows_only_own_open_tasks_grouped(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['name' => 'Đợt hôm nay']);
        $volunteer = User::factory()->volunteer()->create();
        Task::factory()->for($batch, 'batch')->create(['assignee_id' => $volunteer->id, 'title' => 'Việc quá hạn', 'due_at' => now()->subHour()]);
        Task::factory()->for($batch, 'batch')->create(['assignee_id' => $volunteer->id, 'title' => 'Việc tuần sau', 'due_at' => now()->addWeek()]);
        Task::factory()->for($batch, 'batch')->create(['assignee_id' => User::factory()->volunteer(), 'title' => 'Việc của người khác']);
        $cancelled = CookingBatch::factory()->status(BatchStatus::Cancelled)->create();
        Task::factory()->for($cancelled, 'batch')->create(['assignee_id' => $volunteer->id, 'title' => 'Việc đợt đã hủy']);

        $this->actingAs($volunteer)->get('/cong-viec-cua-toi')
            ->assertOk()
            ->assertSeeInOrder(['Hôm nay, quá hạn', 'Việc quá hạn', '(quá hạn)', 'Sắp tới', 'Việc tuần sau'])
            ->assertSee('Đánh dấu hoàn thành')
            ->assertSee('Báo đang bị vướng')
            ->assertDontSee('Việc của người khác')
            ->assertDontSee('Việc đợt đã hủy');
    }

    public function test_my_tasks_empty_state(): void
    {
        $this->actingAs(User::factory()->volunteer()->create())->get('/cong-viec-cua-toi')
            ->assertOk()->assertSee('Không có việc gấp hôm nay');
    }
}
