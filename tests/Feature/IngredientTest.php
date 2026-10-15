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
        $this->assignToBatch($batch, $volunteer);

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
        $this->assignToBatch($batch, $reporter, $other);
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
            ->assertSeeInOrder(['Thực tế đã dùng:', '—'])
            ->assertSee('không phải lượng đã có sẵn');

        $this->assertNull(Ingredient::first()->actual_quantity);
    }

    public function test_volunteer_cannot_add_edit_or_delete_ingredients(): void
    {
        $batch = CookingBatch::factory()->create();
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create();
        $volunteer = User::factory()->volunteer()->create();
        $this->assignToBatch($batch, $volunteer);

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

        $volunteer = User::factory()->volunteer()->create();
        $this->assignToBatch($batch, $volunteer);
        $this->actingAs($volunteer)
            ->post("/admin/ingredients/{$ingredient->id}/shortage")->assertForbidden();
    }

    public function test_quantity_accepts_thousand_separator_and_unit_price_is_optional(): void
    {
        $batch = CookingBatch::factory()->create();

        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Gạo', 'expected_quantity' => '1.500,5', 'actual_quantity' => '1.200', 'unit' => 'kg', 'unit_price_vnd' => '25.000',
        ])->assertRedirect();
        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Muối', 'expected_quantity' => '2', 'unit' => 'gói', 'unit_price_vnd' => '',
        ])->assertRedirect();

        $rice = Ingredient::where('name', 'Gạo')->firstOrFail();
        $this->assertSame('1500.500', $rice->expected_quantity);
        $this->assertSame('1200.000', $rice->actual_quantity);
        $this->assertSame(25_000, $rice->unit_price_vnd);
        $this->assertSame(30_000_000, $rice->costVnd()); // theo lượng thực tế 1.200 kg
        $this->assertNull(Ingredient::where('name', 'Muối')->firstOrFail()->unit_price_vnd);

        // Form sửa hiển thị lại số lượng dạng Việt Nam
        $this->actingAs($batch->coordinator)->get("/admin/ingredients/{$rice->id}/edit")
            ->assertSee('value="1.500,5"', false)->assertSee('value="25.000"', false);

        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Rau', 'expected_quantity' => '1', 'unit' => 'bó', 'unit_price_vnd' => '-5',
        ])->assertSessionHasErrors('unit_price_vnd');
    }

    public function test_ingredient_cost_uses_expected_quantity_until_actual_and_sums_for_batch(): void
    {
        $batch = CookingBatch::factory()->create();
        Ingredient::factory()->for($batch, 'batch')->create(['name' => 'Đậu hũ', 'expected_quantity' => 30, 'unit' => 'cái', 'unit_price_vnd' => 5_000]);
        Ingredient::factory()->for($batch, 'batch')->create(['name' => 'Gạo', 'expected_quantity' => 10, 'actual_quantity' => 2.5, 'unit' => 'kg', 'unit_price_vnd' => 20_000]);
        Ingredient::factory()->for($batch, 'batch')->create(['name' => 'Muối', 'expected_quantity' => 1, 'unit' => 'gói']);

        $this->assertSame(200_000, $batch->fresh()->ingredientCostVnd()); // 30 × 5.000 + 2,5 × 20.000

        $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}")
            ->assertSee('150.000 đ')->assertSee('theo lượng dự kiến')
            ->assertSee('50.000 đ')->assertSee('theo lượng thực tế')
            ->assertSeeInOrder(['Tổng tiền nguyên liệu:', '200.000 đ', '2/3 nguyên liệu có đơn giá']);

        // Tình nguyện viên không xem thông tin tài chính
        $volunteer = User::factory()->volunteer()->create();
        $this->assignToBatch($batch, $volunteer);
        $this->actingAs($volunteer)->get("/admin/batches/{$batch->id}")
            ->assertOk()->assertDontSee('Tổng tiền nguyên liệu')->assertDontSee('Đơn giá:');

        $this->assertNull(CookingBatch::factory()->create()->ingredientCostVnd());
    }
}
