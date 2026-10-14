<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Ảnh nền đợt nấu, slider đợt đang diễn ra trên trang chủ, trang đợt nấu công khai. */
class PublicBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
    }

    /**
     * Dữ liệu form đợt nấu hợp lệ.
     *
     * @param  User  $coordinator  người điều phối
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(User $coordinator, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cơm chay có ảnh',
            'starts_on' => '2026-10-20',
            'expected_servings' => 120,
            'coordinator_id' => $coordinator->id,
        ], $overrides);
    }

    /**
     * Đợt có ảnh nền (chỉ bản ghi; file không cần cho trang HTML).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function batchWithCover(BatchStatus $status, array $attributes = []): CookingBatch
    {
        $batch = CookingBatch::factory()->status($status)->create($attributes);
        $batch->forceFill(['cover_path' => 'batches/2026/10/nen'.$batch->id, 'cover_widths' => [480, 960, 1600], 'cover_width' => 1600, 'cover_height' => 900])->save();

        return $batch;
    }

    public function test_coordinator_uploads_replaces_and_removes_batch_cover(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->postJson('/admin/batches', $this->form($coordinator, [
            'save_as' => 'planned', 'cover' => UploadedFile::fake()->image('nen.jpg', 1200, 800),
        ]))->assertOk();

        $batch = CookingBatch::sole();
        $this->assertStringStartsWith('batches/', $batch->cover_path);
        // Form tạo/sửa gửi được file khi tắt JS
        $this->actingAs($coordinator)->get("/admin/batches/{$batch->id}/edit")
            ->assertSee('enctype="multipart/form-data"', false)->assertSee('name="cover"', false);
        $this->assertSame([480, 960, 1200], $batch->cover_widths); // không phóng to quá ảnh gốc
        Storage::disk('uploads')->assertExists($batch->cover_path.'-960.webp');
        $first = $batch->cover_path;

        // Thay ảnh: file cũ bị xóa
        $this->actingAs($coordinator)->putJson("/admin/batches/{$batch->id}", $this->form($coordinator, [
            'cover' => UploadedFile::fake()->image('moi.png', 800, 600),
        ]))->assertOk();
        $batch->refresh();
        $this->assertNotSame($first, $batch->cover_path);
        Storage::disk('uploads')->assertMissing($first.'-960.webp');
        Storage::disk('uploads')->assertExists($batch->cover_path.'-800.webp');

        // Sửa thông tin khác không đụng tới ảnh
        $this->actingAs($coordinator)->putJson("/admin/batches/{$batch->id}", $this->form($coordinator, ['name' => 'Tên mới']))->assertOk();
        $this->assertSame($batch->cover_path, $batch->fresh()->cover_path);

        // Xóa ảnh nền
        $second = $batch->cover_path;
        $this->actingAs($coordinator)->putJson("/admin/batches/{$batch->id}", $this->form($coordinator, ['remove_cover' => 1]))->assertOk();
        $this->assertNull($batch->fresh()->cover_path);
        Storage::disk('uploads')->assertMissing($second.'-800.webp');
    }

    public function test_batch_cover_rejects_non_images_and_volunteers(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $this->actingAs($coordinator)->postJson('/admin/batches', $this->form($coordinator, [
            'save_as' => 'planned', 'cover' => UploadedFile::fake()->create('tai-lieu.pdf', 10, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors('cover');

        $batch = CookingBatch::factory()->create();
        $this->actingAs(User::factory()->volunteer()->create())->put("/admin/batches/{$batch->id}", $this->form($coordinator, [
            'cover' => UploadedFile::fake()->image('nen.jpg', 600, 400),
        ]))->assertForbidden();
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_home_slider_shows_only_in_progress_batches_with_date_and_link(): void
    {
        $live = $this->batchWithCover(BatchStatus::InProgress, ['name' => 'Đợt đang nấu', 'starts_on' => '2026-10-14 06:30:00']);
        $other = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['name' => 'Đợt thứ hai không ảnh']);
        CookingBatch::factory()->status(BatchStatus::Planned)->create(['name' => 'Đợt kế hoạch']);
        CookingBatch::factory()->status(BatchStatus::Completed)->create(['name' => 'Đợt đã xong']);

        $html = $this->get('/')->assertOk()
            ->assertSee('data-slider', false)
            ->assertSee('Đợt đang nấu')->assertSee('Đợt thứ hai không ảnh')
            ->assertDontSee('Đợt kế hoạch')->assertDontSee('Đợt đã xong')
            ->assertSee(route('blog.batch', $live))
            ->assertSee('14/10/2026')
            ->getContent();

        $slider = (string) str($html)->after('data-slider')->before('</section>');
        $this->assertStringNotContainsString('06:30', $slider); // chỉ ngày, không giờ
        $this->assertStringContainsString('fetchpriority="high"', $slider); // ảnh slide đầu tải ngay
        $this->assertStringContainsString('batch-slide-media is-placeholder', $slider); // đợt không ảnh: nền thay thế
        $this->assertStringContainsString('data-slider-next', $slider);

        // Không lặp lại ở trang lọc / trang 2
        $this->get('/?q=xyz')->assertDontSee('data-slider', false);
        $this->get('/?page=2')->assertDontSee('data-slider', false);
    }

    public function test_home_has_no_slider_without_in_progress_batches(): void
    {
        CookingBatch::factory()->status(BatchStatus::Planned)->create();

        $this->get('/')->assertOk()->assertDontSee('data-slider', false);
    }

    public function test_public_batch_page_shows_summary_and_paginated_posts(): void
    {
        $batch = $this->batchWithCover(BatchStatus::InProgress, [
            'name' => 'Cháo thứ năm', 'starts_on' => '2026-10-15 05:00:00', 'location_text' => 'Bếp bí mật', 'notes' => 'Ghi chú nội bộ',
        ]);
        foreach (range(1, 11) as $i) {
            Post::factory()->published()->create(['cooking_batch_id' => $batch->id, 'title' => "Tin số {$i}", 'published_at' => now()->subHours($i)]);
        }
        Post::factory()->create(['cooking_batch_id' => $batch->id, 'title' => 'Bản nháp ẩn']);
        Post::factory()->published()->create(['title' => 'Bài của đợt khác']);

        $this->get("/dot-nau/{$batch->public_id}")->assertOk()
            ->assertSee('Cháo thứ năm')->assertSee('15/10/2026')->assertSee('Đang diễn ra')
            ->assertSee('11 bài viết')
            ->assertSee('Tin số 1')->assertDontSee('Tin số 11')
            ->assertDontSee('Bản nháp ẩn')->assertDontSee('Bài của đợt khác')
            ->assertDontSee('Bếp bí mật')->assertDontSee('Ghi chú nội bộ')->assertDontSee($batch->coordinator->name)
            ->assertSee('<link rel="canonical" href="'.route('blog.batch', $batch).'">', false);

        $this->get("/dot-nau/{$batch->public_id}?page=2")->assertOk()->assertSee('Tin số 11')->assertDontSee('Tin số 2<', false);
    }

    public function test_public_batch_page_hidden_for_unpublished_batches(): void
    {
        $draft = CookingBatch::factory()->status(BatchStatus::Draft)->create();
        $planned = CookingBatch::factory()->status(BatchStatus::Planned)->create();
        Post::factory()->create(['cooking_batch_id' => $planned->id]); // chỉ có bản nháp
        $completed = CookingBatch::factory()->status(BatchStatus::Completed)->create(['name' => 'Đợt cũ có bài']);
        Post::factory()->published()->create(['cooking_batch_id' => $completed->id, 'title' => 'Nhìn lại đợt cũ']);

        $this->get("/dot-nau/{$draft->public_id}")->assertNotFound();
        $this->get("/dot-nau/{$planned->public_id}")->assertNotFound();
        $this->get('/dot-nau/999999')->assertNotFound();
        $this->get("/dot-nau/{$completed->id}")->assertNotFound(); // không dùng ID tăng dần trong URL
        $this->get('/dot-nau/'.str_repeat('0', 16))->assertNotFound();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $completed->public_id);
        $this->assertNotSame($draft->public_id, $planned->public_id);
        $this->assertSame(url('/dot-nau/'.$completed->public_id), route('blog.batch', $completed));
        $this->get('/dot-nau/abc')->assertNotFound();
        $this->get("/dot-nau/{$completed->public_id}")->assertOk()->assertSee('Đợt cũ có bài')->assertSee('Nhìn lại đợt cũ');

        // Thẻ bài viết và trang chi tiết dẫn tới trang đợt
        $post = Post::where('title', 'Nhìn lại đợt cũ')->sole();
        $this->get('/')->assertSee(route('blog.batch', $completed));
        $this->get("/bai-viet/{$post->slug}")->assertSee(route('blog.batch', $completed));

        // Sitemap: chỉ đợt công khai được + trang liên hệ
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>'.route('blog.batch', $completed).'</loc>', false)
            ->assertDontSee('<loc>'.route('blog.batch', $draft).'</loc>', false)
            ->assertDontSee('<loc>'.route('blog.batch', $planned).'</loc>', false)
            ->assertSee('<loc>'.route('contact.create').'</loc>', false);
    }
}
