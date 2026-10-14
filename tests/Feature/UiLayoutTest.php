<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_page_shows_single_next_step_action_per_status(): void
    {
        $expected = [
            BatchStatus::Draft->value => 'Chốt kế hoạch',
            BatchStatus::Planned->value => 'Bắt đầu nấu',
            BatchStatus::InProgress->value => 'Hoàn tất đợt',
            BatchStatus::Completed->value => 'Mở lại đợt',
            BatchStatus::Cancelled->value => 'Mở lại đợt',
        ];

        foreach ($expected as $status => $label) {
            $batch = CookingBatch::factory()->status(BatchStatus::from($status))->create();
            $this->actingAs($batch->coordinator)->get("/batches/{$batch->id}")
                ->assertOk()
                ->assertSee('class="btn btn-'.($label === 'Mở lại đợt' ? 'outline-warning' : 'primary').'"', false)
                ->assertSee($label);
        }
    }

    public function test_volunteer_sees_no_management_actions_on_batch_page(): void
    {
        $batch = CookingBatch::factory()->create();

        $this->actingAs(User::factory()->volunteer()->create())->get("/batches/{$batch->id}")
            ->assertOk()
            ->assertDontSee('Bắt đầu nấu')
            ->assertDontSee('Hủy đợt')
            ->assertDontSee('Thao tác khác với đợt nấu')
            ->assertSee('In checklist');
    }

    public function test_navigation_shows_open_task_count_and_role_based_items(): void
    {
        $volunteer = User::factory()->volunteer()->create();
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        Task::factory()->count(2)->for($batch, 'batch')->create(['assignee_id' => $volunteer->id]);

        $this->actingAs($volunteer)->get('/')
            ->assertSee('aria-label="2 việc đang mở"', false)
            ->assertDontSee('Quản lý tài khoản')
            ->assertDontSee(route('reports.index'));

        $this->actingAs(User::factory()->admin()->create())->get('/')
            ->assertSee('Quản lý tài khoản')
            ->assertSee(route('reports.index'));
    }

    public function test_admin_user_forms_render_role_choices(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->recorder()->create();

        $this->actingAs($admin)->get('/users/create')->assertOk()
            ->assertSee('Tình nguyện viên')->assertSee('Quản trị hệ thống')->assertSee('Tạo tài khoản');

        $response = $this->actingAs($admin)->get("/users/{$user->id}/edit");
        $response->assertOk()->assertSee('Để trống nếu không đổi.');
        $this->assertMatchesRegularExpression('/id="role_recorder" value="recorder"\s+checked/', $response->getContent());
    }

    public function test_lists_declare_skeleton_targets_and_csv_links_skip_page_loader(): void
    {
        $recorder = User::factory()->recorder()->create();
        $batch = CookingBatch::factory()->create();

        $this->actingAs($recorder)->get('/reports')->assertOk()
            ->assertSee('data-skeleton="table"', false)
            ->assertSee('data-skeleton="list"', false)
            ->assertSee('download data-no-loading', false)
            ->assertSee('data-loading-text="Đang tìm…"', false);

        $this->actingAs($recorder)->get("/reports/batches/{$batch->id}")->assertSee('download data-no-loading', false);
        $this->actingAs($recorder)->get('/batches')->assertSee('data-skeleton="cards"', false);
        $this->actingAs(User::factory()->admin()->create())->get('/users')->assertSee('data-skeleton="list"', false);
    }

    public function test_assets_are_cache_busted_and_login_page_loads_script(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('js/app.js?v='.filemtime(public_path('js/app.js')), false)
            ->assertSee('data-loading-text="Đang đăng nhập…"', false);

        $this->actingAs(User::factory()->create())->get('/')
            ->assertSee('css/app.css?v='.filemtime(public_path('css/app.css')), false);
    }

    public function test_dashboard_period_selector_marks_active_period(): void
    {
        $this->actingAs(User::factory()->coordinator()->create())->get('/?days=90')
            ->assertOk()
            ->assertSee('class="active"  aria-current="true" >90 ngày qua', false);
    }
}
