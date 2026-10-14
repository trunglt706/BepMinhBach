<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\BatchNotificationChannel;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Ingredient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Kênh thông báo của đợt nấu: cấu hình, bảo mật token, gửi sự kiện vào Slack/Telegram/Chatwork. */
class NotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    private const SLACK_HOOK = 'https://hooks.slack.com/services/T000TEST/B000TEST/abcdefghijklmnopqrstuvwx';

    private const TG_TOKEN = '123456789:AAHtestTokenForUnitTestsOnly_abcdefghi';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); // không bao giờ gửi thật ra ngoài trong test
    }

    public function test_coordinator_adds_slack_channel_and_token_is_encrypted_and_never_shown(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Planned)->create();
        $coordinator = $batch->coordinator;

        $this->actingAs($coordinator)->get("/admin/batches/{$batch->id}/channels/create?modal=1")->assertOk()
            ->assertSee('Incoming Webhook URL')->assertSee('Bot token')->assertSee('Room ID');

        $this->actingAs($coordinator)->postJson("/admin/batches/{$batch->id}/channels", [
            'driver' => 'slack',
            'settings' => ['webhook_url' => self::SLACK_HOOK],
            'join_urls' => ['slack' => 'https://join.slack.com/t/bep/shared_invite/zt-abc'],
            'is_enabled' => 1,
        ])->assertOk();

        $channel = BatchNotificationChannel::sole();
        $this->assertSame(self::SLACK_HOOK, $channel->setting('webhook_url'));
        $raw = DB::table('batch_notification_channels')->value('settings');
        $this->assertStringNotContainsString('hooks.slack.com', $raw); // lưu dạng mã hóa
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwx', json_encode($channel->toArray()));

        $page = $this->actingAs($coordinator)->get("/admin/batches/{$batch->id}")->assertOk()
            ->assertSee('Kênh thông báo')->assertSee('Tham gia kênh Slack')
            ->assertSee('https://join.slack.com/t/bep/shared_invite/zt-abc')->getContent();
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwx', $page);
        $edit = $this->actingAs($coordinator)->get("/admin/channels/{$channel->id}/edit")->assertOk()->getContent();
        $this->assertStringNotContainsString(self::SLACK_HOOK, $edit);
        $this->assertStringContainsString('Để trống để giữ giá trị đã lưu (••••uvwx)', $edit);

        // Sửa mà để trống ô bí mật → giữ token cũ
        $this->actingAs($coordinator)->putJson("/admin/channels/{$channel->id}", [
            'settings' => ['webhook_url' => ''], 'join_urls' => ['slack' => ''], 'is_enabled' => 0,
        ])->assertOk();
        $channel->refresh();
        $this->assertSame(self::SLACK_HOOK, $channel->setting('webhook_url'));
        $this->assertFalse($channel->is_enabled);
        $this->assertNull($channel->join_url);
    }

    public function test_validation_per_driver_rejects_foreign_hosts_and_bad_tokens(): void
    {
        $batch = CookingBatch::factory()->create();
        $coordinator = $batch->coordinator;
        $url = "/admin/batches/{$batch->id}/channels";

        // Webhook không phải hooks.slack.com (chống gửi request tới địa chỉ tùy ý)
        $this->actingAs($coordinator)->postJson($url, ['driver' => 'slack', 'settings' => ['webhook_url' => 'http://127.0.0.1/services/xxxxxxxxxxxxxxxxxxxxxxxx']])
            ->assertStatus(422)->assertJsonValidationErrors('settings.webhook_url');
        // Telegram: token/chat id sai định dạng, link tham gia không phải t.me
        $this->actingAs($coordinator)->postJson($url, [
            'driver' => 'telegram', 'settings' => ['bot_token' => 'abc', 'chat_id' => 'nhom bep'],
            'join_urls' => ['telegram' => 'https://evil.example.com/join'],
        ])->assertStatus(422)->assertJsonValidationErrors(['settings.bot_token', 'settings.chat_id', 'join_url']);
        // Chatwork thiếu thông số
        $this->actingAs($coordinator)->postJson($url, ['driver' => 'chatwork', 'settings' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['settings.api_token', 'settings.room_id']);
        // Loại kênh lạ
        $this->actingAs($coordinator)->postJson($url, ['driver' => 'zalo'])->assertStatus(422)->assertJsonValidationErrors('driver');

        // Mỗi loại kênh chỉ một lần cho mỗi đợt
        BatchNotificationChannel::factory()->create(['cooking_batch_id' => $batch->id]);
        $this->actingAs($coordinator)->postJson($url, ['driver' => 'slack', 'settings' => ['webhook_url' => self::SLACK_HOOK]])
            ->assertStatus(422)->assertJsonValidationErrors('driver');

        $this->assertSame(1, BatchNotificationChannel::count());
    }

    public function test_only_coordinators_manage_channels_but_everyone_sees_join_link(): void
    {
        $channel = BatchNotificationChannel::factory()->telegram()->create();
        $batch = $channel->batch;
        $disabled = BatchNotificationChannel::factory()->chatwork()->create(['cooking_batch_id' => $batch->id, 'is_enabled' => false]);
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)->get("/admin/batches/{$batch->id}")->assertOk()
            ->assertSee('Tham gia kênh Telegram')->assertSee('https://t.me/+testInvite')
            ->assertDontSee('Tham gia kênh Chatwork') // kênh đang tắt: chỉ người quản lý thấy
            ->assertDontSee('Gửi thử')->assertDontSee('-1001234567890');

        $this->actingAs($volunteer)->get("/admin/batches/{$batch->id}/channels/create")->assertForbidden();
        $this->actingAs($volunteer)->post("/admin/batches/{$batch->id}/channels", ['driver' => 'slack'])->assertForbidden();
        $this->actingAs($volunteer)->get("/admin/channels/{$channel->id}/edit")->assertForbidden();
        $this->actingAs($volunteer)->put("/admin/channels/{$channel->id}", [])->assertForbidden();
        $this->actingAs($volunteer)->post("/admin/channels/{$channel->id}/test")->assertForbidden();
        $this->actingAs($volunteer)->post("/admin/channels/{$disabled->id}/toggle")->assertForbidden();
        $this->actingAs($volunteer)->delete("/admin/channels/{$channel->id}")->assertForbidden();
        $this->assertSame(2, BatchNotificationChannel::count());
    }

    public function test_batch_events_are_pushed_to_every_enabled_channel(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok'),
            'api.telegram.org/*' => Http::response(['ok' => true]),
            'api.chatwork.com/*' => Http::response(['message_id' => '1']),
        ]);
        $batch = CookingBatch::factory()->status(BatchStatus::Planned)->create(['name' => 'Cháo [thứ năm]']);
        BatchNotificationChannel::factory()->create(['cooking_batch_id' => $batch->id]);
        BatchNotificationChannel::factory()->telegram()->create(['cooking_batch_id' => $batch->id]);
        BatchNotificationChannel::factory()->chatwork()->create(['cooking_batch_id' => $batch->id]);

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/status", ['to' => 'in_progress'])->assertOk();

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === self::SLACK_HOOK
            && str_contains($r['text'], 'Chuyển trạng thái: Đã lên kế hoạch → Đang diễn ra')
            && str_contains($r['text'], route('batches.show', $batch)));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.telegram.org/bot'.self::TG_TOKEN.'/sendMessage'
            && $r['chat_id'] === '-1001234567890' && str_contains($r['text'], $batch->coordinator->name));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.chatwork.com/v2/rooms/123456789/messages'
            && $r->hasHeader('X-ChatWorkToken', 'abcdef0123456789abcdef0123456789')
            && str_contains($r['body'], 'Cháo ［thứ năm］') // ngoặc vuông không tạo được thẻ Chatwork
            && str_starts_with($r['body'], '[info][title]'));

        $this->assertNotNull(BatchNotificationChannel::first()->last_sent_at);
    }

    public function test_task_assignment_shortage_and_blocked_are_sent_without_free_text_or_costs(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        BatchNotificationChannel::factory()->create(['cooking_batch_id' => $batch->id]);
        $volunteer = User::factory()->volunteer()->create(['name' => 'Chị Lan']);
        $coordinator = $batch->coordinator;

        // Thêm việc có giao người
        $this->actingAs($coordinator)->postJson("/admin/batches/{$batch->id}/tasks", ['title' => 'Nấu cháo', 'assignee_id' => $volunteer->id])->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Thêm việc “Nấu cháo” cho Chị Lan'));
        // Tên người phụ trách KHÔNG nằm trong activity log
        $this->assertDatabaseMissing('activity_logs', ['summary' => 'Thêm việc “Nấu cháo” cho Chị Lan.']);

        // Bị vướng: không gửi lý do (chữ tự do)
        $task = Task::where('title', 'Nấu cháo')->sole();
        $this->actingAs($volunteer)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'blocked', 'blocked_note' => 'Bếp ga nhà chị Lan số 12 hỏng'])->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Việc “Nấu cháo”: Đang bị vướng.'));
        Http::assertNotSent(fn (Request $r) => str_contains($r['text'], 'Bếp ga'));

        // Báo thiếu nguyên liệu: không gửi ghi chú
        $ingredient = Ingredient::factory()->for($batch, 'batch')->create(['name' => 'Gạo']);
        $this->actingAs($volunteer)->postJson("/admin/ingredients/{$ingredient->id}/shortage", ['restock_note' => 'Gọi 0901234567'])->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Báo thiếu nguyên liệu “Gạo”'));
        Http::assertNotSent(fn (Request $r) => str_contains($r['text'], '0901234567'));

        // Chi phí: không gửi
        $sent = count(Http::recorded());
        $this->actingAs($coordinator)->postJson("/admin/batches/{$batch->id}/expenses", ['name' => 'Gạo nếp', 'amount_vnd' => '500.000'])->assertOk();
        $this->assertSame(1, ExpenseItem::count());
        $this->assertCount($sent, Http::recorded());
    }

    public function test_disabled_channel_and_other_batches_receive_nothing(): void
    {
        Http::fake();
        $batch = CookingBatch::factory()->status(BatchStatus::Planned)->create();
        BatchNotificationChannel::factory()->create(['cooking_batch_id' => $batch->id, 'is_enabled' => false]);
        BatchNotificationChannel::factory()->telegram()->create(); // kênh của đợt khác

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/status", ['to' => 'in_progress'])->assertOk();

        Http::assertNothingSent();
    }

    public function test_delivery_failure_is_recorded_without_leaking_token_and_does_not_break_action(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);
        $channel = BatchNotificationChannel::factory()->telegram()->create();
        $batch = $channel->batch;
        $batch->forceFill(['status' => BatchStatus::Planned])->save();

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/status", ['to' => 'in_progress'])->assertOk();
        $this->assertSame(BatchStatus::InProgress, $batch->fresh()->status); // thao tác vẫn thành công

        $channel->refresh();
        $this->assertTrue($channel->isFailing());
        $this->assertStringContainsString('chat not found', $channel->last_error);
        $this->assertStringNotContainsString(self::TG_TOKEN, $channel->last_error);

        // Gửi thử: báo lỗi rõ ràng (422 + lý do), trang hiện lỗi cho người quản lý
        $this->actingAs($batch->coordinator)->postJson("/admin/channels/{$channel->id}/test")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Chưa gửi được') && str_contains($m, 'chat not found'));
        $this->actingAs($batch->coordinator)->get("/admin/batches/{$batch->id}")->assertSee('Lỗi gửi')->assertSee('chat not found');
    }

    public function test_test_message_and_toggle_and_delete(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
        $channel = BatchNotificationChannel::factory()->create();
        $coordinator = $channel->batch->coordinator;

        $this->actingAs($coordinator)->postJson("/admin/channels/{$channel->id}/test")->assertOk()->assertJsonPath('message', 'Đã gửi tin thử vào Slack.');
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Tin thử'));

        $this->actingAs($coordinator)->postJson("/admin/channels/{$channel->id}/toggle")->assertOk();
        $this->assertFalse($channel->fresh()->is_enabled);

        $this->actingAs($coordinator)->deleteJson("/admin/channels/{$channel->id}")->assertOk();
        $this->assertSame(0, BatchNotificationChannel::count());
    }

    public function test_publishing_post_about_batch_is_announced_with_public_link(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
        $channel = BatchNotificationChannel::factory()->create();
        $batch = $channel->batch;

        $this->actingAs($batch->coordinator)->postJson('/admin/posts', [
            'title' => 'Cảm ơn đội bếp', 'body' => '<div>Nội dung</div>', 'cooking_batch_id' => $batch->id, 'save_as' => 'published',
        ])->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Đăng bài viết “Cảm ơn đội bếp”: '.url('/bai-viet/')));
    }
}
