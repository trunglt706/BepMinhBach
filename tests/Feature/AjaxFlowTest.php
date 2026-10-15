<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Ingredient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Luồng thao tác bằng modal + AJAX: phản hồi JSON thay cho redirect. */
class AjaxFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_opened_as_modal_returns_only_modal_content(): void
    {
        $batch = CookingBatch::factory()->create();

        $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}/tasks/create?modal=1")
            ->assertOk()
            ->assertSee('class="modal-content" data-ajax', false)
            ->assertSee('id="bmb-modal-title"', false)
            ->assertDontSee('app-sidebar')
            ->assertDontSee('<html', false);

        // Mở bình thường (không JS): vẫn là trang đầy đủ, gửi form thường.
        $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}/tasks/create")
            ->assertOk()
            ->assertSee('app-sidebar')
            ->assertDontSee('data-ajax novalidate', false);
    }

    public function test_modal_forms_render_for_all_short_actions(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $task = Task::factory()->for($batch, 'batch')->create();
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create();
        $expense = ExpenseItem::factory()->for($batch, 'batch')->create(['amount_vnd' => 1500000]);
        $user = $batch->coordinator;

        foreach ([
            "/admin/batches/{$batch->id}/ingredients/create", "/admin/ingredients/{$ingredient->id}/edit",
            "/admin/batches/{$batch->id}/tasks/create", "/admin/tasks/{$task->id}/edit",
            "/admin/batches/{$batch->id}/expenses/create", "/admin/expenses/{$expense->id}/edit",
            "/admin/batches/{$batch->id}/results", "/admin/batches/{$batch->id}/complete", "/admin/batches/{$batch->id}/cancel",
        ] as $url) {
            $this->actingAs($user)->get($url.'?modal=1')->assertOk()->assertSee('modal-footer', false);
        }

        // Ô tiền hiển thị sẵn dạng có dấu chấm, chỉ nhập số
        $this->actingAs($user)->get("/admin/expenses/{$expense->id}/edit?modal=1")
            ->assertSee('value="1.500.000"', false)
            ->assertSee('inputmode="numeric" autocomplete="off" data-money', false);
    }

    public function test_batch_page_links_short_actions_to_modals_and_marks_region(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        Task::factory()->for($batch, 'batch')->create(['assignee_id' => $batch->coordinator_id]);

        $html = $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}")->assertOk()->getContent();

        $this->assertStringContainsString('id="batch-page" data-region', $html);
        $this->assertStringContainsString(route('batches.tasks.create', $batch).'" data-modal', $html);
        $this->assertStringContainsString(route('batches.complete', $batch).'" data-modal', $html);
        $this->assertMatchesRegularExpression('#action="'.preg_quote(route('tasks.status', Task::first()), '#').'" data-ajax#', $html);
        $this->assertStringContainsString('id="bmb-confirm"', $html);
    }

    public function test_store_returns_json_instead_of_redirect(): void
    {
        $batch = CookingBatch::factory()->create();

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/tasks", ['title' => 'Nấu cơm'])
            ->assertOk()
            ->assertExactJson(['message' => 'Đã thêm việc “Nấu cơm”.']);

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Gạo', 'expected_quantity' => '2', 'unit' => 'kg', 'add_another' => '1',
        ])->assertOk()->assertJson(['keepOpen' => true]);

        $this->assertDatabaseHas('tasks', ['title' => 'Nấu cơm']);
        $this->assertDatabaseHas('ingredients', ['name' => 'Gạo']);
    }

    public function test_validation_errors_are_returned_as_json_422(): void
    {
        $batch = CookingBatch::factory()->create();

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/expenses", ['name' => '', 'amount_vnd' => '-5'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Vui lòng nhập tên khoản chi.', 'amount_vnd']);
    }

    public function test_money_with_format_characters_is_normalized_by_backend(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();

        // Phòng khi JS không chạy: backend tự bỏ dấu chấm phân cách.
        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/expenses", ['name' => 'Gas', 'amount_vnd' => '1.250.000'])
            ->assertOk();
        $this->assertDatabaseHas('expense_items', ['name' => 'Gas', 'amount_vnd' => 1250000]);

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/expenses", ['name' => 'Sai', 'amount_vnd' => '12,5'])
            ->assertStatus(422)->assertJsonValidationErrors('amount_vnd');
    }

    public function test_status_actions_and_quick_updates_return_json(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Planned)->create();
        $coordinator = $batch->coordinator;
        $task = Task::factory()->for($batch, 'batch')->create(['assignee_id' => $coordinator->id]);
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create();

        $this->actingAs($coordinator)->postJson("/admin/batches/{$batch->id}/status", ['to' => 'in_progress'])
            ->assertOk()->assertJson(['message' => 'Chương trình đã chuyển sang “Đang diễn ra”.']);

        $this->actingAs($coordinator)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'blocked'])
            ->assertStatus(422)->assertJsonValidationErrors('blocked_note');
        $this->actingAs($coordinator)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'done'])->assertOk();
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);

        $this->actingAs($coordinator)->postJson("/admin/ingredients/{$ingredient->id}/shortage", ['restock_note' => 'Thiếu'])->assertOk();
        $this->actingAs($coordinator)->deleteJson("/admin/ingredients/{$ingredient->id}/shortage")->assertOk();

        $this->actingAs($coordinator)->postJson("/admin/batches/{$batch->id}/complete", [])->assertStatus(422)->assertJsonValidationErrors('confirm');
        $this->actingAs($coordinator)->postJson("/admin/batches/{$batch->id}/complete", ['confirm' => '1'])->assertOk();
        $this->assertSame(BatchStatus::Completed, $batch->fresh()->status);
    }

    public function test_delete_returns_json(): void
    {
        $batch = CookingBatch::factory()->create();
        $task = Task::factory()->for($batch, 'batch')->create(['title' => 'Dọn bếp']);

        $this->actingAs($batch->coordinator)->deleteJson("/admin/tasks/{$task->id}")
            ->assertOk()->assertJson(['message' => 'Đã xóa việc “Dọn bếp”.']);
        $this->assertModelMissing($task);
    }

    public function test_results_conflict_returns_409_with_reload_flag(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $batch->forceFill(['results_version' => 2])->save();

        $this->actingAs($batch->coordinator)->putJson("/admin/batches/{$batch->id}/results", ['prepared_servings' => 10, 'results_version' => 1])
            ->assertStatus(409)->assertJson(['reload' => true]);
        $this->assertNull($batch->fresh()->prepared_servings);
    }

    public function test_authorization_still_enforced_for_json_requests(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $task = Task::factory()->for($batch, 'batch')->create(['assignee_id' => User::factory()->volunteer()]);
        $intruder = User::factory()->volunteer()->create();
        $this->assignToBatch($batch, $intruder);

        $this->actingAs($intruder)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertForbidden()->assertJson(['message' => 'Bạn chỉ cập nhật được công việc được giao cho mình.']);
        $this->actingAs($intruder)->get("/admin/batches/{$batch->id}/tasks/create?modal=1")->assertForbidden();
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);

        // Phiên hết hạn: JSON 401 để giao diện báo và tải lại trang đăng nhập.
        auth()->guard('web')->logout();
        $this->postJson("/admin/tasks/{$task->id}/status", ['status' => 'done'])->assertUnauthorized();
    }

    public function test_long_forms_also_render_as_modal_and_are_linked_with_data_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = CookingBatch::factory()->status(BatchStatus::Draft)->create(['coordinator_id' => $admin->id, 'estimated_cost_vnd' => 2500000]);

        foreach (['/admin/batches/create', "/admin/batches/{$batch->id}/edit", '/admin/users/create', "/admin/users/{$admin->id}/edit", '/admin/account/password'] as $url) {
            $this->actingAs($admin)->get($url.'?modal=1')->assertOk()
                ->assertSee('class="modal-content" data-ajax', false)
                ->assertDontSee('app-sidebar')
                ->assertDontSee('danger-title', false); // không lồng form xóa trong modal
        }
        $this->actingAs($admin)->get("/admin/batches/{$batch->id}/edit?modal=1")->assertSee('value="2.500.000"', false);

        // Mỗi ô mật khẩu có nút xem/ẩn, không điền sẵn giá trị.
        foreach (['/admin/account/password?modal=1' => ['f_current_password', 'f_password', 'f_password_confirmation'],
            "/admin/users/{$admin->id}/edit?modal=1" => ['f_password', 'f_password_confirmation']] as $url => $ids) {
            $html = $this->actingAs($admin)->get($url)->getContent();
            foreach ($ids as $id) {
                $this->assertStringContainsString('data-toggle-password="'.$id.'"', $html);
            }
            $this->assertDoesNotMatchRegularExpression('#type="password"[^>]*value=#', $html);
        }
        // Trang đầy đủ (không JS) vẫn có vùng xóa chương trình nháp, form gửi bình thường.
        $this->actingAs($admin)->get("/admin/batches/{$batch->id}/edit")->assertOk()->assertSee('danger-title', false)->assertSee('app-sidebar');

        $this->actingAs($admin)->get('/admin/batches')->assertSee(route('batches.create').'" data-modal', false);
        $this->actingAs($admin)->get('/admin/users')->assertSee(route('users.create').'" data-modal', false)
            ->assertSee(route('users.edit', $admin).'" data-modal', false)
            ->assertSee(route('account.password').'" data-modal', false);

        $html = $this->actingAs($admin)->get("/admin/batches/{$batch->id}")->getContent();
        $this->assertStringContainsString(route('batches.edit', $batch).'" data-modal', $html);
        $this->assertMatchesRegularExpression('#action="'.preg_quote(route('batches.destroy', $batch), '#').'" data-ajax\s+data-confirm#', $html);
    }

    public function test_batch_create_update_delete_return_json(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $response = $this->actingAs($coordinator)->postJson('/admin/batches', [
            'name' => 'Cơm chay CN', 'starts_on' => now()->addDay()->format('Y-m-d'), 'expected_servings' => 100,
            'coordinator_id' => $coordinator->id, 'estimated_cost_vnd' => '3.200.000', 'save_as' => 'draft',
        ])->assertOk();
        $batch = CookingBatch::firstWhere('name', 'Cơm chay CN');
        $this->assertSame(3_200_000, $batch->estimated_cost_vnd);
        // Chương trình mới có trang riêng: chuyển trang một lần, thông báo được flash cho trang đích.
        $response->assertJson(['redirect' => route('batches.show', $batch)])->assertSessionHas('success');

        $this->actingAs($coordinator)->putJson("/admin/batches/{$batch->id}", [
            'name' => 'Cơm chay CN (sửa)', 'starts_on' => now()->addDay()->format('Y-m-d'), 'expected_servings' => 120,
            'coordinator_id' => $coordinator->id, 'estimated_cost_vnd' => '4.000.000 đ',
        ])->assertOk()->assertExactJson(['message' => 'Đã lưu thay đổi.']);
        $this->assertSame(4_000_000, $batch->fresh()->estimated_cost_vnd);

        $this->actingAs($coordinator)->putJson("/admin/batches/{$batch->id}", ['name' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'starts_on']);

        $this->actingAs($coordinator)->deleteJson("/admin/batches/{$batch->id}")
            ->assertOk()->assertJson(['redirect' => route('batches.index')]);
        $this->assertModelMissing($batch);
    }

    public function test_user_store_update_and_password_change_return_json(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/admin/users', [
            'name' => 'Tình nguyện viên A', 'email' => 'tnv-a@example.test', 'role' => 'volunteer',
            'password' => 'matkhau-123', 'password_confirmation' => 'matkhau-123',
        ])->assertOk()->assertExactJson(['message' => 'Đã tạo tài khoản cho Tình nguyện viên A.']);
        $user = User::firstWhere('email', 'tnv-a@example.test');

        $this->actingAs($admin)->putJson("/admin/users/{$user->id}", ['name' => 'TNV A', 'email' => $user->email, 'role' => 'recorder'])
            ->assertOk()->assertJsonMissing(['redirect']);
        $this->assertSame('TNV A', $user->fresh()->name);

        $this->flushSession(); // đổi người dùng = phiên mới (auth.session lưu mã băm mật khẩu theo phiên)
        $this->actingAs($user)->putJson('/admin/account/password', ['current_password' => 'sai', 'password' => 'moi-12345', 'password_confirmation' => 'moi-12345'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->actingAs($user)->putJson('/admin/account/password', ['current_password' => 'matkhau-123', 'password' => 'moi-12345', 'password_confirmation' => 'moi-12345'])
            ->assertOk()->assertExactJson(['message' => 'Đã đổi mật khẩu.', 'refresh' => false]);

        $volunteer = User::factory()->volunteer()->create();
        $this->flushSession();
        $this->actingAs($volunteer)->postJson('/admin/users', ['name' => 'X'])->assertForbidden();
        $this->actingAs($volunteer)->get('/admin/users/create?modal=1')->assertForbidden();
    }

    public function test_admin_toggle_user_returns_json(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->postJson("/admin/users/{$user->id}/toggle-active")->assertOk();
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_no_unsaved_changes_warning_on_modal_close_or_page_leave(): void
    {
        $js = (string) file_get_contents(public_path('js/app.js'));

        // Không hỏi lại khi đóng modal hay rời trang có form chưa lưu (yêu cầu 14/10/2026).
        $this->assertStringNotContainsString('Bỏ các thay đổi chưa lưu?', $js);
        $this->assertStringNotContainsString("addEventListener('hide.bs.modal'", $js);
        $this->assertStringNotContainsString('beforeunload', $js);
        $this->assertStringNotContainsString('data-warn-unsaved', $js);

        $coordinator = User::factory()->coordinator()->create();
        $this->actingAs($coordinator)->get('/admin/batches/create?modal=1')->assertOk()->assertDontSee('data-warn-unsaved', false);
        $this->actingAs($coordinator)->get('/admin/batches/create')->assertOk()->assertDontSee('data-warn-unsaved', false);
    }
}
