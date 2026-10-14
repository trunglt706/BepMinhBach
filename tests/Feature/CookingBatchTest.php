<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\BatchStatus;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Ingredient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookingBatchTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(User $coordinator, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cơm chay chủ nhật',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'location_text' => 'Bếp thử nghiệm',
            'expected_servings' => '150',
            'coordinator_id' => $coordinator->id,
            'estimated_cost_vnd' => '1.500.000',
            'notes' => 'Ghi chú',
            'save_as' => 'planned',
        ], $overrides);
    }

    /** T06 */
    public function test_coordinator_can_create_batch(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $response = $this->actingAs($coordinator)->post('/batches', $this->validPayload($coordinator));

        $batch = CookingBatch::firstOrFail();
        $response->assertRedirect(route('batches.show', $batch));
        $this->assertSame(BatchStatus::Planned, $batch->status);
        $this->assertSame(150, $batch->expected_servings);
        $this->assertSame(1_500_000, $batch->estimated_cost_vnd);
        $this->assertSame($coordinator->id, $batch->created_by);
        $this->assertNull($batch->prepared_servings, 'Không tự điền số suất thực tế');
        $this->assertDatabaseHas('activity_logs', ['cooking_batch_id' => $batch->id, 'action' => 'batch.created']);
    }

    public function test_coordinator_can_save_as_draft(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->post('/batches', $this->validPayload($coordinator, ['save_as' => 'draft']));

        $this->assertSame(BatchStatus::Draft, CookingBatch::firstOrFail()->status);
    }

    /** T07 */
    public function test_invalid_batch_data_is_rejected(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($coordinator)->from('/batches/create')->post('/batches', $this->validPayload($coordinator, [
            'name' => '',
            'starts_at' => 'khong-phai-ngay',
            'expected_servings' => '-5',
            'estimated_cost_vnd' => '-100',
            'coordinator_id' => $volunteer->id,
        ]))
            ->assertRedirect('/batches/create')
            ->assertSessionHasErrors(['name', 'starts_at', 'expected_servings', 'estimated_cost_vnd', 'coordinator_id']);

        $this->actingAs($coordinator)->post('/batches', $this->validPayload($coordinator, ['expected_servings' => '10.5']))
            ->assertSessionHasErrors('expected_servings');

        $this->assertDatabaseCount('cooking_batches', 0);
    }

    /** T29 — lỗi hiển thị cạnh trường, giữ dữ liệu đã nhập. */
    public function test_validation_errors_are_shown_next_to_fields_and_input_kept(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->from('/batches/create')->followingRedirects()
            ->post('/batches', $this->validPayload($coordinator, ['name' => 'Giữ lại tên', 'expected_servings' => '']))
            ->assertOk()
            ->assertSee('Vui lòng nhập số suất dự kiến.')
            ->assertSee('id="f_expected_servings_error"', false)
            ->assertSee('aria-describedby="f_expected_servings_error"', false)
            ->assertSee('value="Giữ lại tên"', false);
    }

    /** T03 */
    public function test_volunteer_and_recorder_cannot_create_edit_or_delete_batches(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Draft)->create();

        foreach ([User::factory()->volunteer()->create(), User::factory()->recorder()->create()] as $user) {
            $this->actingAs($user)->get('/batches/create')->assertForbidden();
            $this->actingAs($user)->post('/batches', $this->validPayload($batch->coordinator))->assertForbidden();
            $this->actingAs($user)->get("/batches/{$batch->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/batches/{$batch->id}", $this->validPayload($batch->coordinator, ['save_as' => null]))->assertForbidden();
            $this->actingAs($user)->delete("/batches/{$batch->id}")->assertForbidden();
            $this->actingAs($user)->post("/batches/{$batch->id}/status", ['to' => 'planned'])->assertForbidden();
        }

        $this->assertModelExists($batch);
        $this->assertSame(BatchStatus::Draft, $batch->fresh()->status);
    }

    public function test_volunteer_can_view_batch_but_sees_no_edit_buttons(): void
    {
        $batch = CookingBatch::factory()->create();
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)->get("/batches/{$batch->id}")
            ->assertOk()
            ->assertSee($batch->name)
            ->assertDontSee('Sửa thông tin')
            ->assertDontSee('Thêm nguyên liệu')
            ->assertDontSee('Chi phí nguyên liệu');
    }

    public function test_coordinator_can_update_batch(): void
    {
        $batch = CookingBatch::factory()->create();
        $coordinator = $batch->coordinator;

        $payload = $this->validPayload($coordinator, ['name' => 'Tên mới', 'save_as' => null]);
        unset($payload['save_as']);

        $this->actingAs($coordinator)->put("/batches/{$batch->id}", $payload)
            ->assertRedirect(route('batches.show', $batch));

        $this->assertSame('Tên mới', $batch->fresh()->name);
    }

    /** T08 */
    public function test_status_workflow_transitions(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Draft)->create();
        $coordinator = $batch->coordinator;

        $this->actingAs($coordinator)->post("/batches/{$batch->id}/status", ['to' => 'planned']);
        $this->assertSame(BatchStatus::Planned, $batch->fresh()->status);

        $this->actingAs($coordinator)->post("/batches/{$batch->id}/status", ['to' => 'in_progress']);
        $this->assertSame(BatchStatus::InProgress, $batch->fresh()->status);

        // Không nhảy thẳng sang hoàn tất qua thao tác đơn giản
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/status", ['to' => 'completed'])
            ->assertSessionHasErrors('to');
        $this->assertSame(BatchStatus::InProgress, $batch->fresh()->status);

        // Hoàn tất cần xác nhận
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/complete", ['completion_notes' => 'Ổn'])
            ->assertSessionHasErrors('confirm');
        $this->assertSame(BatchStatus::InProgress, $batch->fresh()->status);

        $this->actingAs($coordinator)->post("/batches/{$batch->id}/complete", ['completion_notes' => 'Ổn', 'confirm' => '1'])
            ->assertRedirect(route('batches.show', $batch));
        $batch->refresh();
        $this->assertSame(BatchStatus::Completed, $batch->status);
        $this->assertNotNull($batch->completed_at);
        $this->assertSame('Ổn', $batch->completion_notes);
    }

    public function test_complete_page_lists_missing_items_without_inventing_data(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        Task::factory()->for($batch, 'batch')->create();

        $this->actingAs($batch->coordinator)->get("/batches/{$batch->id}/complete")
            ->assertOk()
            ->assertSee('Còn 1 công việc chưa hoàn thành.')
            ->assertSee('Chưa nhập số suất đã chuẩn bị.')
            ->assertSee('Chưa nhập khoản chi phí nào.');
    }

    public function test_draft_batch_cannot_be_completed(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Draft)->create();

        $this->actingAs($batch->coordinator)->post("/batches/{$batch->id}/complete", ['confirm' => '1'])->assertForbidden();
    }

    /** T09 */
    public function test_cancelled_batch_rejects_normal_updates(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $coordinator = $batch->coordinator;
        $task = Task::factory()->for($batch, 'batch')->create(['assignee_id' => $coordinator->id]);

        $this->actingAs($coordinator)->post("/batches/{$batch->id}/cancel", ['cancellation_reason' => 'Mưa lớn'])
            ->assertSessionHasErrors('confirm');
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/cancel", ['cancellation_reason' => 'Mưa lớn', 'confirm' => '1']);

        $batch->refresh();
        $this->assertSame(BatchStatus::Cancelled, $batch->status);
        $this->assertSame('Mưa lớn', $batch->cancellation_reason);
        $this->assertModelExists($task);

        $this->actingAs($coordinator)->put("/batches/{$batch->id}", array_diff_key($this->validPayload($coordinator), ['save_as' => 1]))->assertForbidden();
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/ingredients", ['name' => 'Gạo', 'expected_quantity' => '1', 'unit' => 'kg'])->assertForbidden();
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/tasks", ['title' => 'Việc mới'])->assertForbidden();
        $this->actingAs($coordinator)->post("/tasks/{$task->id}/status", ['status' => 'done'])->assertForbidden();
        $this->actingAs($coordinator)->put("/batches/{$batch->id}/results", ['prepared_servings' => 10, 'results_version' => 0])->assertForbidden();
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/expenses", ['name' => 'Gas', 'amount_vnd' => '1000'])->assertForbidden();
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/status", ['to' => 'planned'])->assertForbidden();
    }

    public function test_reopen_requires_reason_and_confirmation_and_is_logged(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Completed)->create();
        $coordinator = $batch->coordinator;

        $this->actingAs($coordinator)->post("/batches/{$batch->id}/reopen", ['confirm' => '1'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($coordinator)->post("/batches/{$batch->id}/reopen", ['reason' => 'Nhập sai số suất'])
            ->assertSessionHasErrors('confirm');
        $this->assertSame(BatchStatus::Completed, $batch->fresh()->status);

        $this->actingAs($coordinator)->post("/batches/{$batch->id}/reopen", ['reason' => 'Nhập sai số suất', 'confirm' => '1']);
        $this->assertSame(BatchStatus::InProgress, $batch->fresh()->status);
        // Lý do (chữ tự do) lưu ở đợt nấu; lịch sử chỉ ghi việc mở lại, không chép lý do.
        $this->assertSame('Nhập sai số suất', $batch->fresh()->reopen_reason);
        $this->assertNotNull($batch->fresh()->reopened_at);
        $log = ActivityLog::where('action', ActivityAction::BatchReopened)->firstOrFail();
        $this->assertStringNotContainsString('Nhập sai số suất', $log->summary);
        $this->assertStringContainsString('có ghi lý do', $log->summary);

        $cancelled = CookingBatch::factory()->status(BatchStatus::Cancelled)->create();
        $this->actingAs($cancelled->coordinator)->post("/batches/{$cancelled->id}/reopen", ['reason' => 'Tổ chức lại', 'confirm' => '1']);
        $this->assertSame(BatchStatus::Draft, $cancelled->fresh()->status);
    }

    public function test_volunteer_cannot_reopen_or_cancel(): void
    {
        $completed = CookingBatch::factory()->status(BatchStatus::Completed)->create();
        $planned = CookingBatch::factory()->create();
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)->post("/batches/{$completed->id}/reopen", ['reason' => 'abc', 'confirm' => '1'])->assertForbidden();
        $this->actingAs($volunteer)->post("/batches/{$planned->id}/cancel", ['confirm' => '1'])->assertForbidden();
    }

    /** T10 */
    public function test_batch_with_history_cannot_be_hard_deleted(): void
    {
        $withIngredient = CookingBatch::factory()->status(BatchStatus::Draft)->create();
        Ingredient::factory()->for($withIngredient, 'batch')->create();
        $planned = CookingBatch::factory()->create();

        $this->actingAs($withIngredient->coordinator)->delete("/batches/{$withIngredient->id}")->assertForbidden();
        $this->actingAs($planned->coordinator)->delete("/batches/{$planned->id}")->assertForbidden();

        $this->assertModelExists($withIngredient);
        $this->assertModelExists($planned);
    }

    public function test_empty_draft_can_be_deleted(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Draft)->create();

        $this->actingAs($batch->coordinator)->delete("/batches/{$batch->id}")->assertRedirect('/batches');
        $this->assertModelMissing($batch);
    }

    public function test_list_search_filter_and_pagination_keep_values(): void
    {
        $user = User::factory()->volunteer()->create();
        $coordinator = User::factory()->coordinator()->create();
        CookingBatch::factory()->count(14)->create(['coordinator_id' => $coordinator->id, 'created_by' => $coordinator->id, 'name' => 'Bếp thứ bảy', 'starts_at' => '2026-11-07 07:00']);
        CookingBatch::factory()->status(BatchStatus::Cancelled)->create(['name' => 'Bếp bị hủy', 'starts_at' => '2026-11-08 07:00']);
        CookingBatch::factory()->create(['name' => 'Bếp tháng trước', 'starts_at' => '2026-09-01 07:00']);

        $response = $this->actingAs($user)->get('/batches?q=thứ+bảy&status=planned&from=2026-11-01&to=2026-11-30');
        // Chip "Đã lên kế hoạch" đang được chọn
        $this->assertMatchesRegularExpression('/class="chip active"\s+aria-current="true"\s*>\s*<i class="bi bi-calendar-check"/', $response->getContent());
        $response->assertOk()
            ->assertSee('Tìm thấy 14 đợt nấu.')
            ->assertDontSee('Bếp bị hủy')
            ->assertDontSee('Bếp tháng trước')
            ->assertSee('value="thứ bảy"', false)
            ->assertSee('value="2026-11-01"', false)
            ->assertSee('<input type="hidden" name="status" value="planned">', false)
            ->assertSee('q=th%E1%BB%A9%20b%E1%BA%A3y', false); // link phân trang giữ bộ lọc

        $this->actingAs($user)->get('/batches?status=cancelled')->assertSee('Bếp bị hủy')->assertDontSee('Bếp thứ bảy');
        $this->actingAs($user)->get('/batches?status=khong-hop-le&from=abc')->assertOk();
    }

    public function test_empty_list_and_no_results_states(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->get('/batches')->assertSee('Chưa có đợt nấu ăn nào')->assertSee('Tạo đợt nấu đầu tiên');
        CookingBatch::factory()->create();
        $this->actingAs($coordinator)->get('/batches?q=khongcogi')->assertSee('Không tìm thấy đợt nấu phù hợp');
    }

    public function test_unknown_batch_returns_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/batches/99999')->assertNotFound();
    }
}
