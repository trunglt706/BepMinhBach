<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientTest extends TestCase
{
    use RefreshDatabase;

    /** T11 */
    public function test_coordinator_can_add_and_edit_ingredient(): void
    {
        $batch = CookingBatch::factory()->create();
        $coordinator = $batch->coordinator;

        $this->actingAs($coordinator)->post("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Gạo', 'expected_quantity' => '12,5', 'unit' => 'kg', 'notes' => 'Gạo tẻ',
        ])->assertRedirect(route('batches.show', $batch).'#nguyen-lieu');

        $ingredient = Ingredient::firstOrFail();
        $this->assertSame('12.500', $ingredient->expected_quantity);
        $this->assertNull($ingredient->actual_quantity);

        $this->actingAs($coordinator)->put("/admin/ingredients/{$ingredient->id}", [
            'name' => 'Gạo tẻ', 'expected_quantity' => '12.5', 'actual_quantity' => '11.25', 'unit' => 'other', 'unit_other' => 'bao 5kg',
        ])->assertRedirect();

        $ingredient->refresh();
        $this->assertSame('Gạo tẻ', $ingredient->name);
        $this->assertSame('11.250', $ingredient->actual_quantity);
        $this->assertSame('bao 5kg', $ingredient->unit);
        $this->assertSame($coordinator->id, $ingredient->updated_by);
    }

    public function test_add_another_returns_to_create_form(): void
    {
        $batch = CookingBatch::factory()->create();

        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Muối', 'expected_quantity' => '1', 'unit' => 'gói', 'add_another' => '1',
        ])->assertRedirect(route('batches.ingredients.create', $batch));
    }

    /** T12 */
    public function test_negative_quantity_and_empty_unit_are_rejected(): void
    {
        $batch = CookingBatch::factory()->create();

        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/ingredients", [
            'name' => '', 'expected_quantity' => '-1', 'actual_quantity' => '-2', 'unit' => 'other', 'unit_other' => '',
        ])->assertSessionHasErrors(['name', 'expected_quantity', 'actual_quantity', 'unit']);

        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Gạo', 'expected_quantity' => '1.23456', 'unit' => 'kg',
        ])->assertSessionHasErrors('expected_quantity');

        $this->assertDatabaseCount('ingredients', 0);
    }

    /** T13 */
    public function test_volunteer_can_report_shortage_and_warning_is_shown(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create(['name' => 'Nấm rơm']);
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)->from(route('batches.show', $batch))
            ->post("/admin/ingredients/{$ingredient->id}/shortage", ['restock_note' => 'Còn thiếu 2 kg'])
            ->assertRedirect(route('batches.show', $batch));

        $ingredient->refresh();
        $this->assertTrue($ingredient->needs_restock);
        $this->assertSame($volunteer->id, $ingredient->restock_reported_by);
        $this->assertNotNull($ingredient->restock_reported_at);

        $this->actingAs($batch->coordinator)->get('/admin')->assertSee('Thiếu nguyên liệu')->assertSee('Nấm rơm')->assertSee('Còn thiếu 2 kg');
        $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}")->assertSee('Cần bổ sung')->assertSee('1 nguyên liệu đang báo thiếu');
    }

    public function test_only_reporter_or_coordinator_can_resolve_shortage(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $reporter = User::factory()->volunteer()->create();
        $other = User::factory()->volunteer()->create();
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create();
        $ingredient->forceFill(['needs_restock' => true, 'restock_reported_by' => $reporter->id, 'restock_reported_at' => now()])->save();

        $this->actingAs($other)->delete("/admin/ingredients/{$ingredient->id}/shortage")->assertForbidden();
        $this->assertTrue($ingredient->fresh()->needs_restock);

        $this->actingAs($reporter)->delete("/admin/ingredients/{$ingredient->id}/shortage")->assertRedirect();
        $this->assertFalse($ingredient->fresh()->needs_restock);
    }

    /** T14 */
    public function test_expected_quantity_is_not_treated_as_actual_or_stock(): void
    {
        $batch = CookingBatch::factory()->create();
        Ingredient::factory()->for($batch, 'batch')->create(['name' => 'Đậu hũ', 'expected_quantity' => 30, 'unit' => 'cái']);

        $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}")
            ->assertSee('<div class="qty-main" data-expected>30 cái</div>', false)
            ->assertSee('chưa ghi nhận')
            ->assertSee('không phải lượng đã có sẵn');

        $this->assertNull(Ingredient::first()->actual_quantity);
    }

    public function test_volunteer_cannot_add_edit_or_delete_ingredients(): void
    {
        $batch = CookingBatch::factory()->create();
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create();
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)->get("/admin/batches/{$batch->id}/ingredients/create")->assertForbidden();
        $this->actingAs($volunteer)->post("/admin/batches/{$batch->id}/ingredients", ['name' => 'X', 'expected_quantity' => '1', 'unit' => 'kg'])->assertForbidden();
        $this->actingAs($volunteer)->put("/admin/ingredients/{$ingredient->id}", ['name' => 'X', 'expected_quantity' => '1', 'unit' => 'kg'])->assertForbidden();
        $this->actingAs($volunteer)->delete("/admin/ingredients/{$ingredient->id}")->assertForbidden();
        $this->assertModelExists($ingredient);
    }

    public function test_coordinator_can_delete_ingredient(): void
    {
        $batch = CookingBatch::factory()->create();
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create();

        $this->actingAs($batch->coordinator)->delete("/admin/ingredients/{$ingredient->id}")->assertRedirect();
        $this->assertModelMissing($ingredient);
    }

    public function test_shortage_cannot_be_reported_on_completed_batch(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Completed)->create();
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create();

        $this->actingAs(User::factory()->volunteer()->create())
            ->post("/admin/ingredients/{$ingredient->id}/shortage")->assertForbidden();
    }
}
