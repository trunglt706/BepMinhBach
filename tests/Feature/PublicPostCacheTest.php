<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\User;
use App\Support\PublicPostCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Bộ nhớ đệm trang bài viết công khai: nhanh hơn khi đọc lại, luôn làm mới khi dữ liệu đổi. */
class PublicPostCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Đếm số câu truy vấn khi chạy $callback.
     *
     * @param  callable  $callback  thao tác cần đo
     */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_second_view_uses_cache_and_still_counts_views(): void
    {
        $post = Post::factory()->published()->create(['cooking_batch_id' => CookingBatch::factory()]);
        Post::factory()->published()->count(2)->create();
        $visit = fn (string $agent) => $this->withHeader('User-Agent', $agent)->get("/bai-viet/{$post->slug}")->assertOk();

        $cold = $this->countQueries(fn () => $visit('Mozilla/5.0 (A)'));
        $warm = $this->countQueries(fn () => $visit('Mozilla/5.0 (B)'));

        $this->assertLessThan($cold, $warm, "Lần xem thứ hai phải ít truy vấn hơn (lạnh: {$cold}, nóng: {$warm}).");
        $this->assertSame(2, $post->fresh()->view_count); // lượt xem vẫn được đếm khi đọc từ bộ nhớ đệm
    }

    public function test_editing_post_or_batch_refreshes_cached_pages(): void
    {
        $coordinator = User::factory()->admin()->create();
        $batch = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['distributed_servings' => null]);
        $post = Post::factory()->published()->create(['title' => 'Tiêu đề cũ', 'cooking_batch_id' => $batch->id]);

        $this->get("/bai-viet/{$post->slug}")->assertSee('Tiêu đề cũ');
        $this->get('/')->assertSee('Tiêu đề cũ');
        $version = PublicPostCache::version();

        // Sửa bài qua giao diện quản lý → trang công khai thấy ngay
        $this->actingAs($coordinator)->putJson("/admin/posts/{$post->id}", [
            'title' => 'Tiêu đề mới', 'body' => 'Nội dung', 'tags' => 'mới', 'cooking_batch_id' => $batch->id,
        ])->assertOk();
        $this->assertGreaterThan($version, PublicPostCache::version());
        auth()->guard('web')->logout();
        $this->get("/bai-viet/{$post->slug}")->assertSee('Tiêu đề mới')->assertSee('#mới');
        $this->get('/')->assertSee('Tiêu đề mới')->assertDontSee('Tiêu đề cũ');

        // Chương trình cập nhật số suất → khung "Thuộc chương trình" cập nhật
        $batch->forceFill(['distributed_servings' => 4321])->save();
        $this->get("/bai-viet/{$post->slug}")->assertSee('4.321');

        // Gỡ bài → không còn trong danh sách đã lưu đệm
        $this->actingAs($coordinator)->postJson("/admin/posts/{$post->id}/unpublish")->assertOk();
        auth()->guard('web')->logout();
        $this->get('/')->assertDontSee('Tiêu đề mới');
        $this->get("/bai-viet/{$post->slug}")->assertNotFound();
    }

    public function test_view_count_does_not_invalidate_cache(): void
    {
        $post = Post::factory()->published()->create();
        $version = PublicPostCache::version();

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get("/bai-viet/{$post->slug}")->assertOk();

        $this->assertSame(1, $post->fresh()->view_count);
        $this->assertSame($version, PublicPostCache::version());
    }

    public function test_unchanged_page_returns_304_with_etag(): void
    {
        $post = Post::factory()->published()->create();

        $first = $this->get('/')->assertOk()->assertHeader('ETag');
        $this->assertStringContainsString('private', $first->headers->get('Cache-Control'));

        $this->withHeader('If-None-Match', $first->headers->get('ETag'))->get('/')->assertStatus(304);

        // Nội dung đổi → ETag đổi → trả trang mới
        $post->update(['title' => 'Đổi tiêu đề']);
        $this->withHeader('If-None-Match', $first->headers->get('ETag'))->get('/')->assertOk()->assertSee('Đổi tiêu đề');
    }
}
