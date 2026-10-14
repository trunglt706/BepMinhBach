<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\PostStatus;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bài viết: quản lý nội bộ (điều phối viên) và trang công khai cho khách. */
class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_creates_post_with_tags_and_batch_via_json(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $batch = CookingBatch::factory()->create();

        $this->actingAs($coordinator)->postJson('/posts', [
            'title' => 'Cơm chay chủ nhật', 'body' => "Đoạn 1\n\nĐoạn 2", 'cooking_batch_id' => $batch->id,
            'tags' => 'Cơm chay, cảm ơn ; cơm CHAY,  ', 'save_as' => 'published',
        ])->assertOk()->assertExactJson(['message' => 'Đã đăng bài “Cơm chay chủ nhật”.']);

        $post = Post::firstWhere('title', 'Cơm chay chủ nhật');
        $this->assertSame('com-chay-chu-nhat', $post->slug);
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame($batch->id, $post->cooking_batch_id);
        $this->assertEqualsCanonicalizing(['Cơm chay', 'cảm ơn'], $post->tags->pluck('name')->all()); // trùng không phân biệt hoa thường
        $this->assertDatabaseHas('activity_logs', ['action' => 'post.created', 'cooking_batch_id' => $batch->id]);

        // Trùng tiêu đề → đường dẫn khác
        $this->actingAs($coordinator)->postJson('/posts', ['title' => 'Cơm chay chủ nhật', 'body' => 'x', 'save_as' => 'draft'])->assertOk();
        $this->assertDatabaseHas('posts', ['slug' => 'com-chay-chu-nhat-2', 'status' => 'draft']);
    }

    public function test_post_validation_errors(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->postJson('/posts', [
            'title' => '', 'body' => '', 'cooking_batch_id' => 99999, 'save_as' => 'x',
            'tags' => implode(',', range(1, 11)),
        ])->assertStatus(422)->assertJsonValidationErrors(['title', 'body', 'cooking_batch_id', 'save_as', 'tags']);

        $this->actingAs($coordinator)->postJson('/posts', ['title' => 'A', 'body' => 'B', 'save_as' => 'draft', 'tags' => str_repeat('a', 41)])
            ->assertStatus(422)->assertJsonValidationErrors(['tags' => 'Mỗi thẻ tối đa 40 ký tự.']);
    }

    public function test_only_coordinators_manage_posts(): void
    {
        $post = Post::factory()->create();

        foreach ([User::factory()->volunteer()->create(), User::factory()->recorder()->create()] as $user) {
            $this->actingAs($user)->get('/posts')->assertForbidden();
            $this->actingAs($user)->get('/posts/create?modal=1')->assertForbidden();
            $this->actingAs($user)->postJson('/posts', ['title' => 'X', 'body' => 'Y', 'save_as' => 'published'])->assertForbidden();
            $this->actingAs($user)->putJson("/posts/{$post->id}", ['title' => 'X', 'body' => 'Y'])->assertForbidden();
            $this->actingAs($user)->postJson("/posts/{$post->id}/publish")->assertForbidden();
            $this->actingAs($user)->deleteJson("/posts/{$post->id}")->assertForbidden();
        }
        $this->assertSame(PostStatus::Draft, $post->fresh()->status);

        auth()->guard('web')->logout();
        $this->get('/posts')->assertRedirect('/login');
    }

    public function test_publish_toggle_keeps_slug_and_first_publish_date(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $post = Post::factory()->create(['title' => 'Tiêu đề đầu']);

        $this->actingAs($coordinator)->postJson("/posts/{$post->id}/publish")->assertOk();
        $firstPublished = $post->fresh()->published_at;
        $this->assertTrue($post->fresh()->isPublished());

        // Đã đăng: đổi tiêu đề không đổi đường dẫn
        $this->actingAs($coordinator)->putJson("/posts/{$post->id}", ['title' => 'Tiêu đề mới', 'body' => 'Nội dung'])->assertOk();
        $this->assertSame('tieu-de-dau', $post->fresh()->slug);

        $this->actingAs($coordinator)->postJson("/posts/{$post->id}/publish")->assertOk(); // gỡ
        $this->assertSame(PostStatus::Draft, $post->fresh()->status);
        $this->travel(1)->days();
        $this->actingAs($coordinator)->postJson("/posts/{$post->id}/publish")->assertOk(); // đăng lại
        $this->assertTrue($post->fresh()->published_at->equalTo($firstPublished));
    }

    public function test_delete_post_prunes_unused_tags(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $post = Post::factory()->create();
        $post->tags()->sync(Tag::findOrCreateMany(['riêng', 'chung'])->pluck('id'));
        Post::factory()->create()->tags()->sync(Tag::where('slug', 'chung')->pluck('id'));

        $this->actingAs($coordinator)->deleteJson("/posts/{$post->id}")->assertOk();
        $this->assertModelMissing($post);
        $this->assertSame(['chung'], Tag::pluck('slug')->all());
    }

    public function test_guest_sees_only_published_posts(): void
    {
        $published = Post::factory()->published()->create(['title' => 'Bài đã đăng']);
        $draft = Post::factory()->create(['title' => 'Bài nháp bí mật']);
        $scheduled = Post::factory()->published(now()->addDay())->create(['title' => 'Bài hẹn giờ']);

        $this->get('/bai-viet')->assertOk()
            ->assertSee('Bài đã đăng')->assertDontSee('Bài nháp bí mật')->assertDontSee('Bài hẹn giờ')
            ->assertDontSee('app-sidebar');
        $this->get("/bai-viet/{$published->slug}")->assertOk()->assertSee('Bài đã đăng');
        $this->get("/bai-viet/{$draft->slug}")->assertNotFound();
        $this->get("/bai-viet/{$scheduled->slug}")->assertNotFound();
        $this->get('/bai-viet/khong-ton-tai')->assertNotFound();

        // Người quản lý xem trước bản nháp; tình nguyện viên thì không.
        $this->actingAs(User::factory()->coordinator()->create())->get("/bai-viet/{$draft->slug}")->assertOk()->assertSee('Xem trước bản nháp');
        $this->actingAs(User::factory()->volunteer()->create())->get("/bai-viet/{$draft->slug}")->assertNotFound();
    }

    public function test_guest_can_search_by_keyword_tag_and_batch(): void
    {
        $batch = CookingBatch::factory()->create(['name' => 'Đợt bánh mì']);
        $a = Post::factory()->published()->create(['title' => 'Cảm ơn nhà hảo tâm', 'body' => 'Rau củ tươi', 'cooking_batch_id' => $batch->id]);
        $b = Post::factory()->published()->create(['title' => 'Mẹo sơ chế', 'body' => 'Cắt rau nhanh']);
        $a->tags()->sync(Tag::findOrCreateMany(['cảm ơn'])->pluck('id'));
        $b->tags()->sync(Tag::findOrCreateMany(['kinh nghiệm'])->pluck('id'));

        $this->get('/bai-viet?q=rau')->assertSee('Cảm ơn nhà hảo tâm')->assertSee('Mẹo sơ chế');
        $this->get('/bai-viet?q=rau+tươi')->assertSee('Cảm ơn nhà hảo tâm')->assertDontSee('Mẹo sơ chế'); // mọi từ khóa
        $this->get('/bai-viet?q=kinh+nghiệm')->assertSee('Mẹo sơ chế')->assertDontSee('Cảm ơn nhà hảo tâm'); // khớp tên thẻ
        $this->get('/bai-viet?q=100%25')->assertOk()->assertSee('Không tìm thấy bài viết phù hợp'); // % không là ký tự đại diện
        $this->get('/bai-viet?tag=kinh-nghiem')->assertSee('Mẹo sơ chế')->assertDontSee('Cảm ơn nhà hảo tâm');
        $this->get("/bai-viet?dot={$batch->id}")->assertSee('Cảm ơn nhà hảo tâm')->assertDontSee('Mẹo sơ chế')
            ->assertSee('<option value="'.$batch->id.'" selected>', false); // bộ lọc giữ giá trị
    }

    public function test_batch_filter_does_not_reveal_batches_without_published_posts(): void
    {
        $internal = CookingBatch::factory()->create(['name' => 'Đợt nội bộ chưa công khai']);
        Post::factory()->create(['cooking_batch_id' => $internal->id]); // chỉ là nháp
        Post::factory()->published()->create(['title' => 'Bài công khai']);

        $this->get("/bai-viet?dot={$internal->id}")->assertOk()
            ->assertDontSee('Đợt nội bộ chưa công khai')
            ->assertSee('Bài công khai'); // bộ lọc không hợp lệ bị bỏ qua
    }

    public function test_post_page_shows_only_public_batch_information(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Completed)->create([
            'name' => 'Đợt cháo dinh dưỡng', 'expected_servings' => 150, 'distributed_servings' => 140,
            'location_text' => 'Bếp bí mật', 'estimated_cost_vnd' => 3_000_000, 'notes' => 'Ghi chú nội bộ',
        ]);
        ExpenseItem::factory()->for($batch, 'batch')->create(['name' => 'Khoản chi nội bộ', 'amount_vnd' => 1_234_000]);
        $post = Post::factory()->published()->create(['cooking_batch_id' => $batch->id, 'body' => '<script>alert(1)</script>']);

        $this->get("/bai-viet/{$post->slug}")->assertOk()
            ->assertSee('Đợt cháo dinh dưỡng')->assertSee('Đã hoàn tất')->assertSee('150')->assertSee('140')
            ->assertSee('Chưa ghi nhận') // đã chuẩn bị chưa nhập
            ->assertDontSee($batch->coordinator->name)
            ->assertDontSee('Bếp bí mật')->assertDontSee('Ghi chú nội bộ')
            ->assertDontSee('Khoản chi nội bộ')->assertDontSee('1.234.000')->assertDontSee('3.000.000')
            ->assertDontSee($post->author->name)
            ->assertDontSee('<script>alert(1)</script>', false);

        // Bài không thuộc đợt nào: không có khung đợt nấu
        $solo = Post::factory()->published()->create();
        $this->get("/bai-viet/{$solo->slug}")->assertOk()->assertDontSee('Thuộc đợt nấu');
    }

    public function test_deleting_draft_batch_keeps_post_but_unlinks_it(): void
    {
        $batch = CookingBatch::factory()->status(BatchStatus::Draft)->create();
        $post = Post::factory()->published()->create(['cooking_batch_id' => $batch->id]);

        $this->actingAs($batch->coordinator)->deleteJson("/batches/{$batch->id}")->assertOk();
        $this->assertNull($post->fresh()->cooking_batch_id);
    }
}
