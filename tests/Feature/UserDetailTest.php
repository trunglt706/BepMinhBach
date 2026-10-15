<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Trang chi tiết tài khoản (quản trị viên): 4 tab và đăng xuất khỏi thiết bị. */
class UserDetailTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';

    private const SAFARI_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';

    /** Thêm một phiên đăng nhập giả của tài khoản vào bảng sessions. */
    private function fakeSession(User $user, string $id, string $ua, int $minutesAgo = 5): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '203.0.113.7', 'user_agent' => $ua,
            'payload' => base64_encode(serialize([])), 'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    public function test_only_admin_can_view_user_detail_and_list_links_to_it(): void
    {
        $user = User::factory()->volunteer()->create(['name' => 'Chị Lan']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/users')->assertSee(route('users.show', $user), false);
        $this->actingAs($admin)->get("/admin/users/{$user->id}")->assertOk()
            ->assertSee('Chị Lan')->assertSee('Thông tin cơ bản')
            ->assertSee('Nhật ký hoạt động')->assertSee('Thông báo đã nhận')->assertSee('Thiết bị đăng nhập');

        foreach ([User::factory()->coordinator()->create(), $user] as $other) {
            $this->flushSession();
            $this->actingAs($other)->get("/admin/users/{$user->id}")->assertForbidden();
        }
    }

    public function test_info_tab_shows_counts_and_unknown_tab_falls_back(): void
    {
        $user = User::factory()->coordinator()->create();
        $batch = CookingBatch::factory()->create(['coordinator_id' => $user->id, 'created_by' => $user->id]);
        $this->assignToBatch($batch, $user);

        $this->actingAs(User::factory()->admin()->create())->get("/admin/users/{$user->id}?tab=hack")->assertOk()
            ->assertSee('Thông tin cơ bản')
            ->assertSeeInOrder(['Chương trình điều phối', '1', 'Chương trình tham gia', '1', 'Việc được giao', '1', '1 việc đã xong']);
    }

    public function test_activity_tab_lists_only_this_users_actions(): void
    {
        $user = User::factory()->recorder()->create();
        $batch = CookingBatch::factory()->create(['name' => 'Chương trình thử nhật ký']);
        ActivityLog::record($batch, $user, ActivityAction::BatchResultsUpdated, 'Cập nhật số suất thử.');
        ActivityLog::record(null, User::factory()->create(), ActivityAction::UserUpdated, 'Thao tác của người khác.');

        $this->actingAs(User::factory()->admin()->create())->get("/admin/users/{$user->id}?tab=activity")->assertOk()
            ->assertSee('Cập nhật số suất thử.')->assertSee('Chương trình thử nhật ký')
            ->assertDontSee('Thao tác của người khác.');
    }

    public function test_notifications_tab_shows_task_assignment_and_reset_email_without_token(): void
    {
        $volunteer = User::factory()->volunteer()->create(['email' => 'lan@example.test']);
        $batch = CookingBatch::factory()->create(['name' => 'Chương trình có thông báo']);
        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/tasks", ['title' => 'Nấu cơm', 'assignee_id' => $volunteer->id])
            ->assertRedirect();
        $token = null;
        Password::sendResetLink(['email' => 'lan@example.test'], function ($user, $t) use (&$token) {
            $token = $t;
            $user->sendPasswordResetNotification($t);
        });

        $this->assertSame(2, $volunteer->notifications()->count());
        $this->assertStringNotContainsString($token, DB::table('notifications')->pluck('data')->implode(' '));

        $this->flushSession();
        $this->actingAs(User::factory()->admin()->create())->get("/admin/users/{$volunteer->id}?tab=notifications")->assertOk()
            ->assertSee('Được giao việc “Nấu cơm”')->assertSee('Chương trình “Chương trình có thông báo”')
            ->assertSee('Email đặt lại mật khẩu');

        // Tự giao cho mình: không tạo thông báo
        $this->flushSession();
        $this->actingAs($batch->coordinator)->post("/admin/batches/{$batch->id}/tasks", ['title' => 'Tự làm', 'assignee_id' => $batch->coordinator->id]);
        $this->assertSame(0, $batch->coordinator->notifications()->count());
    }

    public function test_devices_tab_lists_sessions_without_exposing_session_ids_and_can_log_out(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->volunteer()->create(['remember_token' => 'old-token']);
        $this->fakeSession($user, 'phien-dien-thoai-bi-mat-0000000000000000', self::CHROME_ANDROID);
        $this->fakeSession($user, 'phien-may-tinh-bi-mat-00000000000000000000', self::SAFARI_MAC, 60);
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get("/admin/users/{$user->id}?tab=devices")->assertOk()
            ->assertSee('Chrome trên Android')->assertSee('Safari trên macOS')->assertSee('203.0.113.7')
            ->getContent();
        $this->assertStringNotContainsString('phien-dien-thoai-bi-mat', $html); // không lộ mã phiên

        // Đăng xuất một thiết bị (theo mã băm)
        $this->actingAs($admin)->delete("/admin/users/{$user->id}/devices/".hash('sha256', 'phien-dien-thoai-bi-mat-0000000000000000'))
            ->assertRedirect(route('users.show', [$user, 'tab' => 'devices']));
        $this->assertDatabaseMissing('sessions', ['id' => 'phien-dien-thoai-bi-mat-0000000000000000']);
        $this->assertDatabaseHas('sessions', ['id' => 'phien-may-tinh-bi-mat-00000000000000000000']);
        $this->actingAs($admin)->delete("/admin/users/{$user->id}/devices/".str_repeat('a', 64))->assertNotFound();

        // Đăng xuất mọi thiết bị: xóa phiên + vô hiệu "ghi nhớ đăng nhập"
        $this->actingAs($admin)->delete("/admin/users/{$user->id}/devices")->assertRedirect();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'summary' => 'Đăng xuất tài khoản #'.$user->id.' khỏi mọi thiết bị (1 phiên).']);

        // Không phải quản trị: không được đăng xuất người khác
        $this->flushSession();
        $this->actingAs(User::factory()->coordinator()->create())->delete("/admin/users/{$user->id}/devices")->assertForbidden();
    }
}
