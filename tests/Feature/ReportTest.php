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

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedBatches(): array
    {
        $coordinator = User::factory()->coordinator()->create();
        $common = ['coordinator_id' => $coordinator->id, 'created_by' => $coordinator->id];

        $a = CookingBatch::factory()->status(BatchStatus::Completed)->create($common + [
            'name' => 'Đợt A tháng 10', 'starts_on' => '2026-10-05 07:00', 'expected_servings' => 100,
        ]);
        $a->forceFill(['prepared_servings' => 100, 'distributed_servings' => 90])->save();
        ExpenseItem::factory()->for($a, 'batch')->create(['amount_vnd' => 500_000]);

        $b = CookingBatch::factory()->status(BatchStatus::Completed)->create($common + [
            'name' => 'Đợt B tháng 10', 'starts_on' => '2026-10-12 07:00', 'expected_servings' => 200,
        ]);
        $b->forceFill(['prepared_servings' => 180, 'distributed_servings' => 170])->save();
        ExpenseItem::factory()->for($b, 'batch')->create(['amount_vnd' => 700_000]);

        $c = CookingBatch::factory()->create($common + [
            'name' => 'Đợt C tháng 11', 'starts_on' => '2026-11-02 07:00', 'expected_servings' => 300,
        ]);

        return [$a, $b, $c];
    }

    /** T24 */
    public function test_report_summary_matches_filter_scope(): void
    {
        $this->seedBatches();
        $recorder = User::factory()->recorder()->create();

        $this->actingAs($recorder)->get('/admin/reports?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertSee('Đợt A tháng 10')
            ->assertSee('Đợt B tháng 10')
            ->assertDontSee('Đợt C tháng 11')
            ->assertSeeInOrder(['Số đợt', '2', 'Suất dự kiến', '300', 'Suất đã chuẩn bị', '280', 'Suất đã phân phát', '260', 'Chi phí thực tế', '1.200.000 đ'])
            ->assertSee('Từ 01/10/2026 đến 31/10/2026')
            ->assertSee('Tạo lúc')
            ->assertSee('Số liệu do người dùng ghi nhận; cần đối chiếu thực tế.');

        $this->actingAs($recorder)->get('/admin/reports?status=planned')
            ->assertSee('Đợt C tháng 11')
            ->assertDontSee('Đợt A tháng 10')
            ->assertSee('0/1 đợt đã nhập');
    }

    public function test_reports_are_forbidden_for_volunteers(): void
    {
        [$a] = $this->seedBatches();
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)->get('/admin/reports')->assertForbidden();
        $this->actingAs($volunteer)->get('/admin/reports/export')->assertForbidden();
        $this->actingAs($volunteer)->get("/admin/reports/batches/{$a->id}")->assertForbidden();
        $this->actingAs($volunteer)->get("/admin/reports/batches/{$a->id}/export")->assertForbidden();
    }

    /** T25 */
    public function test_csv_export_has_bom_headers_scope_and_escapes_formulas(): void
    {
        [$a] = $this->seedBatches();
        $a->forceFill(['name' => '=HYPERLINK("http://xau.example")', 'variance_note' => "@SUM(1,2)\nDòng 2, có \"ngoặc\""])->save();
        $recorder = User::factory()->recorder()->create();

        $response = $this->actingAs($recorder)->get('/admin/reports/export?from=2026-10-01&to=2026-10-31');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Mã đợt,"Tên đợt","Ngày bắt đầu"', str_replace('"Mã đợt"', 'Mã đợt', $csv));
        $this->assertStringContainsString('2026-10-05', $csv); // chỉ ngày (cột kiểu date)
        $this->assertStringNotContainsString('2026-10-05 07:00', $csv);
        $this->assertStringContainsString('Từ 01/10/2026 đến 31/10/2026', $csv);
        $this->assertStringContainsString('Số liệu do người dùng ghi nhận', $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://xau.example"")"', $csv);
        $this->assertStringContainsString("'@SUM(1,2)", $csv);
        $this->assertStringContainsString('""ngoặc""', $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('Đợt B tháng 10', $csv);
        $this->assertStringNotContainsString('Đợt C tháng 11', $csv);

        // Dòng dữ liệu: số suất là số, ô "chưa nhập" để trống
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n(?=\d+,)/', $csv));
        $rowB = collect($rows)->first(fn ($r) => ($r[1] ?? null) === 'Đợt B tháng 10');
        $this->assertSame(['200', '180', '170', '-10'], array_slice($rowB, 6, 4));
    }

    public function test_batch_report_and_csv_include_ingredients_tasks_expenses(): void
    {
        [$a] = $this->seedBatches();
        Ingredient::factory()->for($a, 'batch')->create(['name' => 'Gạo lứt', 'expected_quantity' => 2.5, 'unit' => 'kg']);
        Task::factory()->for($a, 'batch')->create(['title' => 'Chia suất']);
        $recorder = User::factory()->recorder()->create();

        $this->actingAs($recorder)->get("/admin/reports/batches/{$a->id}")
            ->assertOk()
            ->assertSee('Gạo lứt')->assertSee('2,5 kg')
            ->assertSee('Chia suất')
            ->assertSee('500.000 đ')
            ->assertSee('Tạo lúc')
            ->assertSee('cần đối chiếu thực tế');

        $csv = $this->actingAs($recorder)->get("/admin/reports/batches/{$a->id}/export")->streamedContent();
        $this->assertStringContainsString('NGUYÊN LIỆU', $csv);
        $this->assertStringContainsString('"Gạo lứt",kg,2.5,,Không', $csv);
        $this->assertStringContainsString('CÔNG VIỆC', $csv);
        $this->assertStringContainsString('CHI PHÍ', $csv);
    }

    /** T26 — báo cáo không có dữ liệu cá nhân người nhận (hệ thống không thu thập) và không lộ email người dùng. */
    public function test_reports_do_not_contain_personal_data(): void
    {
        [$a] = $this->seedBatches();
        $recorder = User::factory()->recorder()->create();

        $csv = $this->actingAs($recorder)->get('/admin/reports/export')->streamedContent();
        $this->assertStringNotContainsString('@example', $csv);
        $this->assertStringNotContainsString($a->coordinator->email, $csv);

        $this->actingAs($recorder)->get("/admin/reports/batches/{$a->id}")->assertDontSee($a->coordinator->email);

        foreach (['cooking_batches', 'ingredients', 'tasks', 'expense_items'] as $table) {
            $columns = \Schema::getColumnListing($table);
            foreach (['recipient', 'phone', 'address', 'diagnosis', 'allergy'] as $sensitive) {
                $this->assertEmpty(preg_grep("/{$sensitive}/i", $columns), "{$table} không được có cột {$sensitive}");
            }
        }
    }

    public function test_report_empty_state(): void
    {
        $this->actingAs(User::factory()->recorder()->create())->get('/admin/reports')
            ->assertOk()->assertSee('Không có đợt nấu nào trong phạm vi này');
    }

    /** T27 */
    public function test_printable_checklist_has_batch_info_and_no_navigation(): void
    {
        $batch = CookingBatch::factory()->create(['name' => 'Đợt in thử']);
        Task::factory()->for($batch, 'batch')->create(['title' => 'Nấu canh', 'assignee_id' => User::factory()->volunteer()->create(['name' => 'TNV In Thử'])]);
        Ingredient::factory()->for($batch, 'batch')->create(['name' => 'Bí đỏ']);

        $this->actingAs(User::factory()->volunteer()->create())->get("/admin/batches/{$batch->id}/print")
            ->assertOk()
            ->assertSee('Checklist: Đợt in thử')
            ->assertSee($batch->starts_on->format('d/m/Y'))
            ->assertSee('Nấu canh')
            ->assertSee('TNV In Thử')
            ->assertSee('Bí đỏ')
            ->assertSee('class="box"', false)
            ->assertDontSee('Điều hướng chính')
            ->assertDontSee('Đăng xuất')
            ->assertSee('không thay thế quy trình hay chứng nhận an toàn thực phẩm');
    }
}
