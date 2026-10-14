<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Biểu đồ ở trang tổng quan, chi tiết đợt nấu; bộ icon rút gọn được preload. */
class ChartAndAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_charts_use_selected_period_and_hide_costs_from_volunteers(): void
    {
        $recorder = User::factory()->recorder()->create();
        $batch = CookingBatch::factory()->status(BatchStatus::Completed)->create([
            'starts_at' => now()->subDays(3), 'expected_servings' => 200, 'distributed_servings' => 180,
        ]);
        ExpenseItem::factory()->for($batch, 'batch')->create(['amount_vnd' => 1_250_000]);
        CookingBatch::factory()->status(BatchStatus::Planned)->create(['starts_at' => now()->addDays(2)]);

        $html = $this->actingAs($recorder)->get('/?days=7')->assertOk()->getContent();
        $this->assertStringContainsString('1 đợt đã nhập số liệu', $html);
        $this->assertStringContainsString('class="col-group"', $html);
        $this->assertStringContainsString('Dự kiến 200; Đã phân phát 180', $html); // aria-label của nhóm cột
        $this->assertStringContainsString('chart-expense-title', $html);
        $this->assertStringContainsString('1.250.000 đ', $html);
        // Trạng thái gồm cả đợt sắp tới
        $this->assertMatchesRegularExpression('#Đã lên kế hoạch\s*</span>\s*<span class="hbar-track">.*?hbar-value">1<#s', $html);

        $volunteer = User::factory()->volunteer()->create();
        $this->actingAs($volunteer)->get('/?days=7')->assertOk()
            ->assertSee('chart-servings-title', false)
            ->assertDontSee('chart-expense-title', false)
            ->assertDontSee('1.250.000');
    }

    public function test_dashboard_charts_show_empty_state_without_results(): void
    {
        CookingBatch::factory()->create(['starts_at' => now()->subDay()]);

        $this->actingAs(User::factory()->coordinator()->create())->get('/')->assertOk()
            ->assertSee('Chưa có số liệu phân phát')
            ->assertDontSee('class="col-group"', false);
    }

    public function test_batch_page_charts_tasks_servings_and_costs(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create([
            'expected_servings' => 100, 'prepared_servings' => 90, 'estimated_cost_vnd' => 2_000_000,
        ]);
        Task::factory()->for($batch, 'batch')->count(2)->create(['status' => TaskStatus::Done]);
        Task::factory()->for($batch, 'batch')->create(['status' => TaskStatus::Blocked, 'blocked_note' => 'Thiếu gas']);
        foreach (['Gạo' => 900_000, 'Rau' => 300_000, '<b>Đậu</b>' => 200_000] as $name => $amount) {
            ExpenseItem::factory()->for($batch, 'batch')->create(['name' => $name, 'amount_vnd' => $amount]);
        }

        $html = $this->actingAs($batch->coordinator)->get("/batches/{$batch->id}")->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Hoàn thành: 2 việc (67%)"', $html);
        $this->assertStringContainsString('aria-label="Đang bị vướng: 1 việc (33%)"', $html);
        $this->assertStringContainsString('90% so với dự kiến', $html);
        $this->assertMatchesRegularExpression('#Đã phân phát\s*</span>\s*<span class="hbar-track">\s*<span class="hbar-value is-empty">Chưa nhập<#', $html);
        $this->assertStringContainsString('70% so với dự kiến', $html); // 1.400.000 / 2.000.000
        $this->assertStringContainsString('Theo khoản chi', $html);
        // Tên khoản chi do người dùng nhập luôn được escape, kể cả trong data-tip
        $this->assertStringNotContainsString('<b>Đậu</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Đậu&lt;/b&gt;', $html);

        // Tình nguyện viên không thấy biểu đồ chi phí
        $volunteer = User::factory()->volunteer()->create();
        $this->actingAs($volunteer)->get("/batches/{$batch->id}")->assertOk()
            ->assertSee('Hoàn thành: 2 việc', false)
            ->assertDontSee('Theo khoản chi')
            ->assertDontSee('Thực tế đã nhập');
    }

    public function test_icon_subset_is_preloaded_and_covers_every_icon_used(): void
    {
        $css = file_get_contents(public_path('vendor/bootstrap-icons/bootstrap-icons.subset.css'));
        preg_match('#url\("(fonts/bootstrap-icons-subset\.woff2\?v=[0-9a-f]{10})"\)#', $css, $m);
        $this->assertNotEmpty($m, 'CSS rút gọn phải trỏ tới font có phiên bản');

        // URL preload trùng URL trong CSS → trình duyệt dùng lại, không tải hai lần.
        $this->get('/login')->assertOk()
            ->assertSee('<link rel="preload" href="'.asset('vendor/bootstrap-icons/'.$m[1]).'" as="font"', false)
            ->assertDontSee('bootstrap-icons.min.css');

        // Mọi icon dùng trong view/PHP/JS phải có trong bộ rút gọn (nếu thiếu: chạy `npm run icons`).
        $json = json_decode(file_get_contents(base_path('node_modules/bootstrap-icons/font/bootstrap-icons.json')) ?: '{}', true);
        if (! $json) {
            $this->markTestSkipped('Chưa cài node_modules; không kiểm tra được danh sách icon.');
        }
        $files = array_merge(
            glob(resource_path('views/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE),
            glob(app_path('{,*/,*/*/}*.php'), GLOB_BRACE),
            [public_path('js/app.js')],
        );
        $missing = [];
        foreach ($files as $file) {
            preg_match_all('/\bbi-([a-z0-9]+(?:-[a-z0-9]+)*)/', file_get_contents($file), $found);
            foreach ($found[1] as $name) {
                if (isset($json[$name]) && ! str_contains($css, '.bi-'.$name.'::before')) {
                    $missing[] = $name;
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)), 'Icon chưa có trong bộ rút gọn — chạy `npm run icons`.');
    }
}
