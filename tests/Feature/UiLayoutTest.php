<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\Post;
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
            BatchStatus::Planned->value => 'Bắt đầu thực hiện',
            BatchStatus::InProgress->value => 'Hoàn tất chương trình',
            BatchStatus::Completed->value => 'Mở lại chương trình',
            BatchStatus::Cancelled->value => 'Mở lại chương trình',
        ];

        foreach ($expected as $status => $label) {
            $batch = CookingBatch::factory()->status(BatchStatus::from($status))->create();
            $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}")
                ->assertOk()
                ->assertSee('class="btn btn-'.($label === 'Mở lại chương trình' ? 'outline-warning' : 'primary').'"', false)
                ->assertSee($label);
        }
    }

    public function test_volunteer_sees_no_management_actions_on_batch_page(): void
    {
        $batch = CookingBatch::factory()->create();

        $volunteer = User::factory()->volunteer()->create();
        $this->assignToBatch($batch, $volunteer);
        $this->actingAs($volunteer)->get("/admin/batches/{$batch->id}")
            ->assertOk()
            ->assertDontSee('Bắt đầu thực hiện')
            ->assertDontSee('Hủy chương trình')
            ->assertDontSee('Thao tác khác với chương trình')
            ->assertSee('In checklist');
    }

    public function test_logout_buttons_ask_for_confirmation(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->getContent();

        // Cả nút ở sidebar (máy tính) lẫn menu tài khoản (điện thoại); không JS vẫn đăng xuất được bình thường
        $this->assertSame(2, substr_count($html, 'action="'.route('logout').'" data-confirm="Bạn muốn đăng xuất khỏi Góp Gió?" data-confirm-label="Đăng xuất"'));
    }

    public function test_navigation_shows_today_task_count_live_batches_and_role_based_items(): void
    {
        $volunteer = User::factory()->volunteer()->create();
        $today = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['starts_on' => today()]);
        $later = CookingBatch::factory()->status(BatchStatus::Planned)->create(['starts_on' => today()->addDays(3)]);
        CookingBatch::factory()->status(BatchStatus::InProgress)->create(['starts_on' => today()->subDay()]);
        CookingBatch::factory()->status(BatchStatus::Completed)->create();

        // Tính: hạn hôm nay; không có hạn nhưng chương trình diễn ra hôm nay
        Task::factory()->for($today, 'batch')->create(['assignee_id' => $volunteer->id, 'due_at' => today()->setTime(9, 0)]);
        Task::factory()->for($today, 'batch')->create(['assignee_id' => $volunteer->id, 'due_at' => null]);
        // Không tính: đã xong, hạn ngày khác, chương trình ngày khác không có hạn, việc của người khác
        Task::factory()->for($today, 'batch')->create(['assignee_id' => $volunteer->id, 'due_at' => today()->setTime(8, 0), 'status' => 'done']);
        Task::factory()->for($later, 'batch')->create(['assignee_id' => $volunteer->id, 'due_at' => today()->addDays(3)]);
        Task::factory()->for($later, 'batch')->create(['assignee_id' => $volunteer->id, 'due_at' => null]);
        Task::factory()->for($today, 'batch')->create(['due_at' => today()->setTime(10, 0)]);

        $this->actingAs($volunteer)->get('/admin')
            ->assertSee('aria-label="2 việc của tôi hôm nay"', false)
            ->assertSee('class="nav-badge is-live" aria-label="1 chương trình đang diễn ra"', false) // chỉ chương trình mình có việc
            ->assertDontSee('Quản lý tài khoản')
            ->assertDontSee(route('reports.index'));

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertSee('Quản lý tài khoản')
            ->assertSee(route('reports.index'));
    }

    public function test_admin_user_forms_render_role_choices(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->recorder()->create();

        $this->actingAs($admin)->get('/admin/users/create')->assertOk()
            ->assertSee('Tình nguyện viên')->assertSee('Quản trị hệ thống')->assertSee('Tạo tài khoản');

        $response = $this->actingAs($admin)->get("/admin/users/{$user->id}/edit");
        $response->assertOk()->assertSee('Để trống nếu không đổi.');
        $this->assertMatchesRegularExpression('/id="role_recorder" value="recorder"\s+checked/', $response->getContent());
    }

    public function test_lists_declare_skeleton_targets_and_csv_links_skip_page_loader(): void
    {
        $recorder = User::factory()->recorder()->create();
        $batch = CookingBatch::factory()->create();
        $this->assignToBatch($batch, $recorder);

        $this->actingAs($recorder)->get('/admin/reports')->assertOk()
            ->assertSee('data-skeleton="table"', false)
            ->assertSee('data-skeleton="list"', false)
            ->assertSee('download data-no-loading', false)
            ->assertSee('data-loading-text="Đang tìm…"', false);

        $this->actingAs($recorder)->get("/admin/reports/batches/{$batch->id}")->assertSee('download data-no-loading', false);
        $this->actingAs($recorder)->get('/admin/batches')->assertSee('data-skeleton="cards"', false);
        $this->actingAs(User::factory()->admin()->create())->get('/admin/users')->assertSee('data-skeleton="list"', false);
    }

    public function test_assets_are_cache_busted_and_login_page_loads_script(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('js/app.js?v='.filemtime(public_path('js/app.js')), false)
            ->assertSee('data-loading-text="Đang đăng nhập…"', false);

        $this->actingAs(User::factory()->create())->get('/admin')
            ->assertSee('css/app.css?v='.filemtime(public_path('css/app.css')), false);
    }

    public function test_dashboard_period_selector_marks_active_period(): void
    {
        $this->actingAs(User::factory()->coordinator()->create())->get('/admin?days=90')
            ->assertOk()
            ->assertSee('class="active"  aria-current="true" >90 ngày qua', false);
    }

    public function test_admin_lists_have_mobile_filter_toggle_and_keep_filter_values(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            '/admin/contacts?q=Lan' => ['contact-filter', 'value="Lan"'],
            '/admin/users?q=Lan&role=volunteer' => ['user-filter', 'value="Lan"'],
            '/admin/posts?q=Lan' => ['post-filter', 'value="Lan"'],
        ] as $url => [$target, $kept]) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('data-filter-toggle="'.$target.'"', false)
                ->assertSee('id="'.$target.'"', false)
                ->assertSee('class="filter-dot"', false)
                ->assertSee($kept, false);
        }

        // Chưa lọc: nút phễu không có chấm báo
        $this->actingAs($admin)->get('/admin/posts')->assertSee('data-filter-toggle="post-filter"', false)->assertDontSee('class="filter-dot"', false);
    }

    public function test_post_list_puts_publish_action_in_menu_on_mobile_and_user_actions_in_one_row(): void
    {
        $admin = User::factory()->admin()->create();
        Post::factory()->create(['title' => 'Bài thử bố cục']);
        User::factory()->volunteer()->create(['name' => 'Người thử']);

        $html = $this->actingAs($admin)->get('/admin/posts')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<li class="d-sm-none">\s*<form method="POST" action="[^"]+/publish"[^>]*>.*?class="dropdown-item">.*?Đăng bài#s', $html);
        $this->assertStringContainsString('class="d-block fw-semibold mb-1"', $html); // tiêu đề riêng một dòng
        $this->assertMatchesRegularExpression('#<span class="meta-pair">\s*<span><i class="bi bi-person"#', $html);

        $this->actingAs($admin)->get('/admin/users')->assertOk()
            ->assertSee('class="d-flex gap-2 flex-shrink-0 row-actions"', false)
            ->assertSee('user-pills', false);
    }
}
