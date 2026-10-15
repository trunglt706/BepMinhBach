<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultsAndExpensesTest extends TestCase
{
    use RefreshDatabase;

    /** T19 */
    public function test_recorder_can_record_servings_and_history_is_kept(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['expected_servings' => 150]);
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);

        $this->actingAs($recorder)->put("/admin/batches/{$batch->id}/results", [
            'prepared_servings' => '148',
            'distributed_servings' => '140',
            'variance_note' => 'Mưa nên ít người',
            'results_version' => '0',
        ])->assertRedirect(route('batches.show', $batch).'#ket-qua');

        $batch->refresh();
        $this->assertSame(148, $batch->prepared_servings);
        $this->assertSame(140, $batch->distributed_servings);
        $this->assertSame($recorder->id, $batch->results_updated_by);
        $this->assertNotNull($batch->results_updated_at);
        $this->assertSame(1, $batch->results_version);
        $this->assertStringContainsString('chuẩn bị chưa nhập → 148', ActivityLog::where('action', 'batch.results')->firstOrFail()->summary);
    }

    /** T20 */
    public function test_variances_are_shown_and_not_auto_corrected(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['expected_servings' => 100]);
        $batch->forceFill(['prepared_servings' => 90, 'distributed_servings' => 95])->save();

        $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}")
            ->assertSee('Số suất đã chuẩn bị ít hơn dự kiến 10 suất.')
            ->assertSee('Số suất đã phân phát nhiều hơn số đã chuẩn bị 5 suất. Vui lòng kiểm tra lại.');

        $batch->refresh();
        $this->assertSame(90, $batch->prepared_servings);
        $this->assertSame(95, $batch->distributed_servings);
    }

    /** T21 */
    public function test_negative_or_decimal_servings_are_rejected(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);

        $this->actingAs($recorder)->put("/admin/batches/{$batch->id}/results", [
            'prepared_servings' => '-1', 'distributed_servings' => '10.5', 'results_version' => '0',
        ])->assertSessionHasErrors(['prepared_servings', 'distributed_servings']);

        $this->assertNull($batch->fresh()->prepared_servings);
    }

    public function test_concurrent_result_edit_is_detected(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);
        $batch->forceFill(['prepared_servings' => 50, 'results_version' => 3])->save();

        $this->actingAs($recorder)->put("/admin/batches/{$batch->id}/results", [
            'prepared_servings' => '10', 'results_version' => '2',
        ])->assertRedirect(route('batches.results.edit', $batch))->assertSessionHas('error');

        $this->assertSame(50, $batch->fresh()->prepared_servings);
    }

    public function test_volunteer_cannot_record_results_or_expenses_or_see_expenses(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $expense = ExpenseItem::factory()->for($batch, 'batch')->create(['name' => 'Khoản bí mật']);
        $volunteer = User::factory()->volunteer()->create();
        $this->assignToBatch($batch, $volunteer);

        $this->actingAs($volunteer)->get("/admin/batches/{$batch->id}/results")->assertForbidden();
        $this->actingAs($volunteer)->put("/admin/batches/{$batch->id}/results", ['prepared_servings' => 5, 'results_version' => 0])->assertForbidden();
        $this->actingAs($volunteer)->post("/admin/batches/{$batch->id}/expenses", ['name' => 'Gas', 'amount_vnd' => '1000'])->assertForbidden();
        $this->actingAs($volunteer)->delete("/admin/expenses/{$expense->id}")->assertForbidden();
        $this->actingAs($volunteer)->get("/admin/batches/{$batch->id}")->assertOk()->assertDontSee('Khoản bí mật');
    }

    public function test_completed_batch_results_need_reopen(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Completed)->create();

        $this->actingAs($batch->coordinator)->put("/admin/batches/{$batch->id}/results", ['prepared_servings' => 5, 'results_version' => 0])
            ->assertForbidden();
    }

    /** T22 */
    public function test_recorder_can_add_expense_in_vnd_with_separators(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);

        $this->actingAs($recorder)->post("/admin/batches/{$batch->id}/expenses", [
            'name' => 'Rau củ', 'amount_vnd' => '350.000', 'note' => 'Chợ sáng',
        ])->assertRedirect();

        $expense = ExpenseItem::firstOrFail();
        $this->assertSame(350000, $expense->amount_vnd);
        $this->assertSame($recorder->id, $expense->recorded_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expense.created']);
    }

    public function test_negative_or_invalid_expense_is_rejected(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);

        foreach (['-1000', 'abc', '12,5', ''] as $amount) {
            $this->actingAs($recorder)->post("/admin/batches/{$batch->id}/expenses", ['name' => 'Gas', 'amount_vnd' => $amount])
                ->assertSessionHasErrors('amount_vnd');
        }
        $this->assertDatabaseCount('expense_items', 0);
    }

    public function test_recorder_can_edit_and_delete_expense(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $expense = ExpenseItem::factory()->for($batch, 'batch')->create(['amount_vnd' => 1000]);
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);

        $this->actingAs($recorder)->put("/admin/expenses/{$expense->id}", ['name' => 'Gas', 'amount_vnd' => '2000'])->assertRedirect();
        $this->assertSame(2000, $expense->fresh()->amount_vnd);

        $this->actingAs($recorder)->delete("/admin/expenses/{$expense->id}")->assertRedirect();
        $this->assertModelMissing($expense);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expense.deleted']);
    }

    /** T23 */
    public function test_expense_total_distinguishes_not_entered_from_zero(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);

        $this->actingAs($recorder)->get("/admin/batches/{$batch->id}")
            ->assertSeeInOrder(['Thực tế đã nhập', '<span class="hbar-value is-empty">—</span>'], false);

        ExpenseItem::factory()->for($batch, 'batch')->create(['amount_vnd' => 0]);
        $this->actingAs($recorder)->get("/admin/batches/{$batch->id}")
            ->assertSeeInOrder(['Thực tế đã nhập', '0 đ']);

        ExpenseItem::factory()->for($batch, 'batch')->create(['amount_vnd' => 1_250_000]);
        ExpenseItem::factory()->for($batch, 'batch')->create(['amount_vnd' => 300_000]);
        $this->actingAs($recorder)->get("/admin/batches/{$batch->id}")
            ->assertSeeInOrder(['Thực tế đã nhập', '1.550.000 đ']);
    }

    public function test_servings_accept_thousand_separator_and_distributed_cannot_exceed_prepared(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['expected_servings' => 2000]);
        $recorder = User::factory()->recorder()->create();
        $this->assignToBatch($batch, $recorder);

        $this->actingAs($recorder)->put("/admin/batches/{$batch->id}/results", [
            'prepared_servings' => '1.200', 'distributed_servings' => '1.250', 'results_version' => '0',
        ])->assertSessionHasErrors(['distributed_servings' => 'Số suất đã phân phát không được vượt quá số đã chuẩn bị (1.200).']);
        $this->assertNull($batch->fresh()->prepared_servings);

        // Chưa nhập số chuẩn bị: vẫn cho nhập số phân phát
        $this->actingAs($recorder)->put("/admin/batches/{$batch->id}/results", [
            'prepared_servings' => '', 'distributed_servings' => '1.000', 'results_version' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('batches.show', $batch).'#ket-qua');
        $this->assertSame(1000, $batch->fresh()->distributed_servings);

        $this->actingAs($recorder)->put("/admin/batches/{$batch->id}/results", [
            'prepared_servings' => '1.200', 'distributed_servings' => '1.200', 'results_version' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1200, $batch->fresh()->prepared_servings);

        $this->actingAs($recorder)->get("/admin/batches/{$batch->id}/results")
            ->assertSee('value="1.200"', false)->assertSee('data-money', false);
    }
}
