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
            ->assertSee('Chưa có chương trình nào')
            ->assertSee('Tạo chương trình đầu tiên')
            ->assertSee('Chưa có số liệu');
    }

    public function test_empty_state_without_cta_for_volunteer(): void
    {
        $this->actingAs(User::factory()->volunteer()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Chưa có chương trình nào')
            ->assertDontSee('Tạo chương trình đầu tiên');
    }

    public function test_dashboard_shows_counts_active_batches_and_my_tasks(): void
    {
        $volunteer = User::factory()->volunteer()->create();
        $running = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['name' => 'Chương trình đang nấu', 'starts_on' => now()]);
        $next = CookingBatch::factory()->create(['name' => 'Chương trình tuần sau', 'starts_on' => now()->addWeek()]);
        $done = CookingBatch::factory()->status(BatchStatus::Completed)->create(['starts_on' => now()->subDays(3)]);
        $done->forceFill(['prepared_servings' => 130, 'distributed_servings' => 120])->save();
        Task::factory()->for($running, 'batch')->create(['assignee_id' => $volunteer->id, 'title' => 'Rửa rau']);
        $this->assignToBatch($next, $volunteer);
        $this->assignToBatch($done, $volunteer);
        CookingBatch::factory()->create(['name' => 'Chương trình không tham gia', 'starts_on' => now()->addDays(2)]);

        $this->actingAs($volunteer)->get('/admin')
            ->assertOk()
            ->assertSee('Chương trình đang nấu')
            ->assertSee('Chương trình tuần sau')
            ->assertSee('Rửa rau')
            ->assertDontSee('Chương trình không tham gia') // chương trình không có việc của mình: không hiện
            ->assertSeeInOrder(['Suất đã phân phát', '120', '1 chương trình có số liệu']);

        // Ngoài khoảng thời gian chọn thì không tính
        $done->forceFill(['starts_on' => now()->subDays(20)])->save();
        $this->actingAs($volunteer)->get('/admin?days=7')->assertSee('Chưa có số liệu');
    }

    public function test_attention_section_is_collapsed_by_default(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        Task::factory()->for($batch, 'batch')->create(['title' => 'Chở gạo', 'status' => 'blocked', 'blocked_note' => 'Xe hỏng']);

        $html = $this->actingAs($batch->coordinator)->get('/admin')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<details class="card[^"]*collapsible-card">\s*<summary[^>]*>\s*<h2[^>]*>.*Cần xử lý</h2>#s', $html);
        $this->assertStringNotContainsString('collapsible-card" open', $html);
        $this->assertStringContainsString('Chở gạo', $html); // nội dung vẫn có sẵn, chỉ thu gọn
    }
}
