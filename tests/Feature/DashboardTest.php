<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** T28 */
    public function test_empty_state_with_cta_for_coordinator(): void
    {
        $this->actingAs(User::factory()->coordinator()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Chưa có đợt nấu ăn nào')
            ->assertSee('Tạo đợt nấu đầu tiên')
            ->assertSee('Chưa có số liệu');
    }

    public function test_empty_state_without_cta_for_volunteer(): void
    {
        $this->actingAs(User::factory()->volunteer()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Chưa có đợt nấu ăn nào')
            ->assertDontSee('Tạo đợt nấu đầu tiên');
    }

    public function test_dashboard_shows_counts_active_batches_and_my_tasks(): void
    {
        $volunteer = User::factory()->volunteer()->create();
        $running = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['name' => 'Đợt đang nấu', 'starts_on' => now()]);
        CookingBatch::factory()->create(['name' => 'Đợt tuần sau', 'starts_on' => now()->addWeek()]);
        $done = CookingBatch::factory()->status(BatchStatus::Completed)->create(['starts_on' => now()->subDays(3)]);
        $done->forceFill(['prepared_servings' => 130, 'distributed_servings' => 120])->save();
        Task::factory()->for($running, 'batch')->create(['assignee_id' => $volunteer->id, 'title' => 'Rửa rau']);

        $this->actingAs($volunteer)->get('/admin')
            ->assertOk()
            ->assertSee('Đợt đang nấu')
            ->assertSee('Đợt tuần sau')
            ->assertSee('Rửa rau')
            ->assertSeeInOrder(['Suất đã phân phát', '120', '1 đợt có số liệu']);

        // Ngoài khoảng thời gian chọn thì không tính
        $done->forceFill(['starts_on' => now()->subDays(20)])->save();
        $this->actingAs($volunteer)->get('/admin?days=7')->assertSee('Chưa có số liệu');
    }
}
