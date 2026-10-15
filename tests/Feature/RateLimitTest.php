<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Chống spam: giới hạn theo từng tài khoản (thao tác ghi), theo IP (trang công khai). */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bmb.rate_limits' => ['writes' => 3, 'reads' => 5, 'public' => 2]]);
    }

    public function test_writes_are_limited_per_user_with_vietnamese_json_message(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create();
        $task = Task::factory()->for($batch, 'batch')->create();
        $coordinator = $batch->coordinator;

        foreach (range(1, 3) as $i) {
            $this->actingAs($coordinator)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk();
        }
        $this->actingAs($coordinator)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', fn (string $m) => (bool) preg_match('/^Bạn thao tác quá nhanh\. Vui lòng đợi \d+ giây rồi thử lại\.$/u', $m));
        $this->assertSame('in_progress', $task->fresh()->status->value);

        // Tài khoản khác (dù cùng IP) không bị ảnh hưởng
        $other = User::factory()->admin()->create();
        $this->actingAs($other)->postJson("/admin/tasks/{$task->id}/status", ['status' => 'done'])->assertOk();

        // Đọc trang có hạn mức riêng, không bị khóa vì đã hết lượt ghi
        $this->actingAs($coordinator)->get("/admin/batches/{$batch->id}")->assertOk();
    }

    public function test_plain_form_submit_over_limit_returns_to_form_with_message_and_input(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        foreach (range(1, 3) as $i) {
            $this->actingAs($coordinator)->post('/admin/posts', ['title' => 'Bài '.$i, 'body' => 'x', 'save_as' => 'draft']);
        }

        $this->actingAs($coordinator)->from('/admin/posts/create')
            ->post('/admin/posts', ['title' => 'Bài thứ tư', 'body' => 'Nội dung dài', 'save_as' => 'draft'])
            ->assertRedirect('/admin/posts/create')
            ->assertSessionHas('error')
            ->assertSessionHasInput('title', 'Bài thứ tư');
        $this->assertDatabaseMissing('posts', ['title' => 'Bài thứ tư']);
    }

    public function test_reads_are_limited_with_429_page(): void
    {
        $user = User::factory()->volunteer()->create();
        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->get('/admin')->assertOk();
        }
        $this->actingAs($user)->get('/admin')->assertStatus(429)->assertSee('Thao tác quá nhanh')->assertSeeText('giây rồi tải lại trang');
    }

    public function test_public_pages_are_limited_per_ip(): void
    {
        $this->get('/')->assertOk();
        $this->get('/')->assertOk();
        $this->get('/')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->get('/')->assertOk();
    }
}
