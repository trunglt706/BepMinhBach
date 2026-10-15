<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Ingredient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phạm vi dữ liệu theo vai trò: quản trị xem mọi chương trình; điều phối viên chỉ chương trình mình điều phối;
 * người ghi nhận / tình nguyện viên chỉ chương trình có việc giao cho mình. Ngoài phạm vi: không có trong danh sách,
 * thống kê, báo cáo; mở trực tiếp thì 404.
 */
class BatchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Chương trình "của tôi" và chương trình "người khác" (cùng tháng 10/2026, đang diễn ra).
     *
     * @return array{0: CookingBatch, 1: CookingBatch}
     */
    private function twoBatches(User $coordinator): array
    {
        $mine = CookingBatch::factory()->status(BatchStatus::InProgress)->create([
            'name' => 'Chương trình của tôi', 'coordinator_id' => $coordinator->id, 'created_by' => $coordinator->id,
            'starts_on' => '2026-10-05', 'expected_servings' => 100,
        ]);
        $others = CookingBatch::factory()->status(BatchStatus::InProgress)->create([
            'name' => 'Chương trình người khác', 'starts_on' => '2026-10-06', 'expected_servings' => 900,
        ]);
        ExpenseItem::factory()->for($mine, 'batch')->create(['amount_vnd' => 100_000]);
        ExpenseItem::factory()->for($others, 'batch')->create(['amount_vnd' => 9_000_000]);

        return [$mine, $others];
    }

    public function test_coordinator_only_sees_batches_they_coordinate(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        [$mine, $others] = $this->twoBatches($coordinator);
        $this->actingAs($coordinator);

        $this->get('/admin/batches')->assertOk()->assertSee('Chương trình của tôi')->assertDontSee('Chương trình người khác');
        $this->get('/admin')->assertOk()->assertSee('Chương trình của tôi')->assertDontSee('Chương trình người khác')
            ->assertSee('aria-label="1 chương trình đang diễn ra"', false);
        $this->get('/admin/reports?from=2026-10-01&to=2026-10-31')->assertOk()
            ->assertSee('Chương trình của tôi')->assertDontSee('Chương trình người khác')
            ->assertSeeInOrder(['Số chương trình', '1', 'Suất dự kiến', '100'])->assertDontSee('9.000.000');
        $csv = $this->get('/admin/reports/export?from=2026-10-01&to=2026-10-31')->streamedContent();
        $this->assertStringContainsString('Chương trình của tôi', $csv);
        $this->assertStringNotContainsString('Chương trình người khác', $csv);

        $this->get("/admin/batches/{$mine->id}")->assertOk();
        foreach (["/admin/batches/{$others->id}", "/admin/batches/{$others->id}/edit", "/admin/batches/{$others->id}/print",
            "/admin/reports/batches/{$others->id}", "/admin/reports/batches/{$others->id}/export",
            "/admin/batches/{$others->id}/results", "/admin/batches/{$others->id}/tasks/create"] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post("/admin/batches/{$others->id}/status", ['to' => 'planned'])->assertNotFound();
        $this->assertSame(BatchStatus::InProgress, $others->fresh()->status);
    }

    public function test_volunteer_and_recorder_only_see_batches_with_their_tasks(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        [$mine, $others] = $this->twoBatches($coordinator);
        $volunteer = User::factory()->volunteer()->create();
        $recorder = User::factory()->recorder()->create();
        $myTask = Task::factory()->for($mine, 'batch')->create(['assignee_id' => $volunteer->id, 'title' => 'Rửa rau']);
        Task::factory()->for($mine, 'batch')->create(['assignee_id' => $recorder->id]);
        $otherTask = Task::factory()->for($others, 'batch')->create(['assignee_id' => User::factory()->volunteer()]);
        $otherIngredient = Ingredient::factory()->for($others, 'batch')->create();

        foreach ([$volunteer, $recorder] as $user) {
            $this->actingAs($user)->get('/admin/batches')->assertSee('Chương trình của tôi')->assertDontSee('Chương trình người khác');
            $this->actingAs($user)->get("/admin/batches/{$mine->id}")->assertOk();
            $this->actingAs($user)->get("/admin/batches/{$others->id}")->assertNotFound();
            $this->actingAs($user)->post("/admin/ingredients/{$otherIngredient->id}/shortage")->assertNotFound();
            $this->actingAs($user)->post("/admin/tasks/{$otherTask->id}/status", ['status' => 'done'])->assertNotFound();
        }

        $this->actingAs($volunteer)->post("/admin/tasks/{$myTask->id}/status", ['status' => 'done'])->assertRedirect();

        // Người ghi nhận: báo cáo / thống kê chỉ trong phạm vi
        $this->actingAs($recorder)->get('/admin/reports?from=2026-10-01&to=2026-10-31')
            ->assertSee('Chương trình của tôi')->assertDontSee('Chương trình người khác')->assertDontSee('9.000.000');
        $this->actingAs($recorder)->get("/admin/reports/batches/{$others->id}")->assertNotFound();
        $this->actingAs($recorder)->put("/admin/batches/{$others->id}/results", ['prepared_servings' => 1, 'results_version' => 0])->assertNotFound();
        $this->assertNull($others->fresh()->prepared_servings);

        // Người chưa có việc nào: danh sách rỗng
        $this->actingAs(User::factory()->volunteer()->create())->get('/admin/batches')
            ->assertDontSee('Chương trình của tôi')->assertDontSee('Chương trình người khác');
    }

    public function test_coordinator_with_task_in_another_program_can_see_and_update_it(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        [, $others] = $this->twoBatches(User::factory()->coordinator()->create());
        $task = Task::factory()->for($others, 'batch')->create(['assignee_id' => $coordinator->id, 'title' => 'Hỗ trợ chia quà']);

        $this->actingAs($coordinator)->get('/admin/my-tasks')->assertOk()->assertSee('Hỗ trợ chia quà')
            ->assertSee(route('tasks.status', $task), false); // có nút cập nhật
        $this->actingAs($coordinator)->get("/admin/batches/{$others->id}")->assertOk();
        $this->actingAs($coordinator)->post("/admin/tasks/{$task->id}/status", ['status' => 'done'])->assertRedirect();
        $this->actingAs($coordinator)->get('/admin/batches')->assertSee('Chương trình người khác');
    }

    public function test_admin_sees_every_batch(): void
    {
        [, $others] = $this->twoBatches(User::factory()->coordinator()->create());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/batches')->assertSee('Chương trình của tôi')->assertSee('Chương trình người khác');
        $this->actingAs($admin)->get("/admin/reports/batches/{$others->id}")->assertOk();
    }

    public function test_coordinator_handing_batch_over_is_redirected_to_list(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $colleague = User::factory()->coordinator()->create(['name' => 'Anh Bình']);
        [$mine] = $this->twoBatches($coordinator);

        $this->actingAs($coordinator)->put("/admin/batches/{$mine->id}", [
            'name' => $mine->name, 'starts_on' => '2026-10-05', 'expected_servings' => '100', 'coordinator_id' => $colleague->id,
        ])->assertRedirect('/admin/batches')->assertSessionHas('success', fn ($m) => str_contains($m, 'Anh Bình'));

        $this->assertSame($colleague->id, $mine->fresh()->coordinator_id);
        $this->actingAs($coordinator)->get("/admin/batches/{$mine->id}")->assertNotFound();
    }

    public function test_posts_can_only_be_linked_to_visible_batches(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        [, $others] = $this->twoBatches($coordinator);

        $this->actingAs($coordinator)->get('/admin/posts/create')->assertOk()
            ->assertSee('Chương trình của tôi')->assertDontSee('Chương trình người khác');
        $this->actingAs($coordinator)->postJson('/admin/posts', [
            'title' => 'Bài thử', 'body' => 'Nội dung', 'cooking_batch_id' => $others->id, 'save_as' => 'draft',
        ])->assertStatus(422)->assertJsonValidationErrors('cooking_batch_id');
    }
}
