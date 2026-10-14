<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Các FormRequest bộ lọc: giá trị lạ trong URL bị bỏ qua (không lỗi), giá trị hợp lệ được giữ. */
class RequestNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_filter_ignores_invalid_values_and_swaps_reversed_dates(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        CookingBatch::factory()->status(BatchStatus::Draft)->create(['name' => 'Đợt tháng 9', 'starts_on' => '2026-09-10 08:00']);
        CookingBatch::factory()->status(BatchStatus::Planned)->create(['name' => 'Đợt tháng 10', 'starts_on' => '2026-10-10 08:00']);

        // Ngày không tồn tại, trạng thái lạ: bỏ qua → thấy cả hai đợt, không lỗi 422/500
        $this->actingAs($coordinator)->get('/admin/batches?from=2026-02-30&to=abc&status=hacked')->assertOk()
            ->assertSee('Đợt tháng 9')->assertSee('Đợt tháng 10');

        // Khoảng ngày bị ngược được đổi chỗ; bộ lọc giữ giá trị trên form
        $this->actingAs($coordinator)->get('/admin/reports?from=2026-10-31&to=2026-10-01')->assertOk()
            ->assertSee('Từ 01/10/2026 đến 31/10/2026')
            ->assertSee('value="2026-10-01"', false);

        $this->actingAs($coordinator)->get('/admin/batches?status='.BatchStatus::Planned->value)->assertOk()
            ->assertSee('Đợt tháng 10')->assertDontSee('Đợt tháng 9');
    }

    public function test_dashboard_period_falls_back_to_default(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/admin?days=999')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#aria-current="true"\s*>30 ngày qua#', $html);
    }

    public function test_user_filter_by_account_state_enum(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Quản trị']);
        User::factory()->create(['name' => 'Người đang hoạt động']);
        User::factory()->locked()->create(['name' => 'Người bị khóa']);

        $this->actingAs($admin)->get('/admin/users?state=locked')->assertOk()
            ->assertSee('Người bị khóa')->assertDontSee('Người đang hoạt động')
            ->assertSee('<option value="locked" selected>', false);
        $this->actingAs($admin)->get('/admin/users?state=xyz&role=root')->assertOk()
            ->assertSee('Người bị khóa')->assertSee('Người đang hoạt động');
    }
}
