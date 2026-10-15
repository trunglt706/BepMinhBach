<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\BatchStatus;
use App\Jobs\SendBatchChannelNotification;
use App\Models\ActivityLog;
use App\Models\BatchNotificationChannel;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Ingredient;
use App\Models\Post;
use App\Models\Task;
use App\Models\User;
use App\Support\BatchNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Kênh thông báo của chương trình: cấu hình, bảo mật token, gửi sự kiện vào Slack/Telegram/Chatwork. */
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

        // Mỗi loại kênh chỉ một lần cho mỗi chương trình
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
        $this->assignToBatch($batch, $volunteer);

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
        BatchNotificationChannel::factory()->telegram()->create(); // kênh của chương trình khác

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
            'title' => 'Cảm ơn đội Góp Gió', 'body' => '<div>Nội dung</div>', 'cooking_batch_id' => $batch->id, 'save_as' => 'pending',
        ])->assertOk();
        Http::assertNothingSent(); // chờ duyệt: chưa công khai, chưa báo kênh
        $this->flushSession();
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/admin/posts/'.Post::sole()->id.'/publish')->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Đăng bài viết “Cảm ơn đội Góp Gió”: '.url('/bai-viet/')));
    }

    public function test_notifications_are_queued_one_job_per_enabled_channel_without_secrets(): void
    {
        Queue::fake();
        $batch = CookingBatch::factory()->status(BatchStatus::Planned)->create();
        $slack = BatchNotificationChannel::factory()->create(['cooking_batch_id' => $batch->id]);
        $telegram = BatchNotificationChannel::factory()->telegram()->create(['cooking_batch_id' => $batch->id]);
        BatchNotificationChannel::factory()->chatwork()->create(['cooking_batch_id' => $batch->id, 'is_enabled' => false]);

        $this->actingAs($batch->coordinator)->postJson("/admin/batches/{$batch->id}/status", ['to' => 'in_progress'])->assertOk();

        Http::assertNothingSent(); // request không chờ gửi mạng
        Queue::assertPushed(SendBatchChannelNotification::class, 2);
        Queue::assertPushed(SendBatchChannelNotification::class, fn ($job) => $job->channelId === $slack->id);
        Queue::assertPushed(SendBatchChannelNotification::class, fn ($job) => $job->channelId === $telegram->id
            && $job->actor === $batch->coordinator->name);
        Queue::assertPushed(SendBatchChannelNotification::class, fn ($job) => ! str_contains(serialize($job), self::TG_TOKEN)
            && ! str_contains(serialize($job), 'hooks.slack.com'));
    }

    public function test_nothing_is_queued_when_the_transaction_rolls_back(): void
    {
        Queue::fake();
        $batch = CookingBatch::factory()->create();
        BatchNotificationChannel::factory()->create(['cooking_batch_id' => $batch->id]);

        try {
            DB::transaction(function () use ($batch) {
                ActivityLog::record($batch, $batch->coordinator, ActivityAction::BatchUpdated, 'Sửa thông tin chương trình.');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        Queue::assertNothingPushed();
    }

    public function test_job_skips_disabled_or_deleted_channel_and_retries_on_failure(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('no_service', 500)]);
        $channel = BatchNotificationChannel::factory()->create();

        $job = (new SendBatchChannelNotification($channel->id, 'Tin', 'Ai đó'))->withFakeQueueInteractions();
        $job->handle(app(BatchNotifier::class));
        $job->assertReleased(30); // lỗi: thử lại sau 30 giây
        $this->assertTrue($channel->fresh()->isFailing());

        $channel->update(['is_enabled' => false]);
        $sent = count(Http::recorded());
        (new SendBatchChannelNotification($channel->id, 'Tin', null))->handle(app(BatchNotifier::class));
        (new SendBatchChannelNotification(999_999, 'Tin', null))->handle(app(BatchNotifier::class));
        $this->assertCount($sent, Http::recorded());
    }

    public function test_every_batch_activity_is_announced_except_costs_and_prices(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        BatchNotificationChannel::factory()->create(['cooking_batch_id' => $batch->id]);
        $coordinator = $batch->coordinator;
        $sentText = fn (string $needle) => Http::assertSent(fn (Request $r) => str_contains($r['text'], $needle));

        // Nguyên liệu: thêm / sửa / xóa — không gửi đơn giá, ghi chú
        $this->actingAs($coordinator)->postJson("/admin/batches/{$batch->id}/ingredients", [
            'name' => 'Gạo', 'expected_quantity' => '1.500', 'unit' => 'kg', 'unit_price_vnd' => '18.000', 'notes' => 'Mua ở chợ Bà Chiểu',
        ])->assertOk();
        $sentText('Thêm nguyên liệu “Gạo”: dự kiến 1.500 kg.');
        $ingredient = Ingredient::sole();
        $this->actingAs($coordinator)->putJson("/admin/ingredients/{$ingredient->id}", [
            'name' => 'Gạo', 'expected_quantity' => '1.500', 'actual_quantity' => '1.200', 'unit' => 'kg', 'unit_price_vnd' => '18.000',
        ])->assertOk();
        $sentText('Sửa nguyên liệu “Gạo”: dự kiến 1.500 kg, thực tế đã dùng 1.200 kg.');
        $sent = count(Http::recorded());
        $this->actingAs($coordinator)->putJson("/admin/ingredients/{$ingredient->id}", [
            'name' => 'Gạo', 'expected_quantity' => '1.500', 'actual_quantity' => '1.200', 'unit' => 'kg', 'unit_price_vnd' => '20.000',
        ])->assertOk();
        $this->assertCount($sent, Http::recorded()); // chỉ đổi đơn giá: không gửi
        $this->actingAs($coordinator)->deleteJson("/admin/ingredients/{$ingredient->id}")->assertOk();
        $sentText('Xóa nguyên liệu “Gạo”.');

        // Công việc: sửa tên/hạn, đổi mọi trạng thái, xóa
        $task = Task::factory()->for($batch, 'batch')->create(['title' => 'Rửa rau']);
        $this->actingAs($coordinator)->putJson("/admin/tasks/{$task->id}", ['title' => 'Rửa rau muống'])->assertOk();
        $sentText('Sửa việc “Rửa rau muống” (trước đây: “Rửa rau”)');
        $this->actingAs($coordinator)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk();
        $sentText('Việc “Rửa rau muống”: Đang làm.');
        $this->actingAs($coordinator)->deleteJson("/admin/tasks/{$task->id}")->assertOk();
        $sentText('Xóa việc “Rửa rau muống”.');

        // Gỡ bài viết về chương trình
        $post = Post::factory()->published()->create(['cooking_batch_id' => $batch->id, 'title' => 'Tin chương trình', 'author_id' => $coordinator->id]);
        $this->actingAs($coordinator)->postJson("/admin/posts/{$post->id}/unpublish")->assertOk();
        $sentText('Gỡ bài viết “Tin chương trình” khỏi trang công khai.');

        Http::assertNotSent(fn (Request $r) => str_contains($r['text'], '18.000') || str_contains($r['text'], '20.000')
            || str_contains($r['text'], 'Bà Chiểu'));
    }

    public function test_send_test_button_lives_in_row_menu(): void
    {
        $channel = BatchNotificationChannel::factory()->create();

        $html = $this->actingAs($channel->batch->coordinator)->get("/admin/batches/{$channel->batch->id}")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<ul class="dropdown-menu[^"]*">.*?action="'.preg_quote(route('channels.test', $channel), '#').'".*?class="dropdown-item">.*?Gửi thử#s', $html);
        $this->assertStringNotContainsString('btn btn-sm btn-outline-secondary" data-loading-text="Đang gửi…"><i class="bi bi-send"', $html);
    }
}
