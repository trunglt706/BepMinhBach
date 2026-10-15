<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quy trình duyệt bài: điều phối viên viết/sửa/xóa bài của mình và gửi duyệt; quản trị viên duyệt (đăng) hoặc từ chối.
 * Trang công khai chỉ thấy bài đã duyệt.
 */
class PostReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_review_flow_submit_reject_resubmit_approve(): void
    {
        $author = User::factory()->coordinator()->create(['name' => 'Chị Mai']);
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['author_id' => $author->id, 'title' => 'Phát quà Trung thu']);

        // Gửi duyệt → chờ duyệt, chưa công khai, quản trị viên nhận thông báo
        $this->actingAs($author)->postJson("/admin/posts/{$post->id}/submit")->assertOk();
        $this->assertSame(PostStatus::Pending, $post->fresh()->status);
        auth()->guard('web')->logout();
        $this->get("/bai-viet/{$post->slug}")->assertNotFound(); // khách chưa thấy
        $this->assertSame('Bài chờ duyệt: “Phát quà Trung thu”', $admin->notifications()->sole()->data['title']);

        // Quản trị viên thấy badge + bài ở đầu danh sách; từ chối kèm lý do → về nháp, tác giả nhận lý do
        $this->flushSession();
        $this->actingAs($admin)->get('/admin/posts')->assertSee('aria-label="1 bài chờ duyệt"', false)
            ->assertSee(route('posts.reject', $post), false);
        $this->actingAs($admin)->postJson("/admin/posts/{$post->id}/reject", ['review_note' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('review_note');
        $this->actingAs($admin)->postJson("/admin/posts/{$post->id}/reject", ['review_note' => 'Ảnh có mặt trẻ em'])->assertOk();
        $post->refresh();
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertSame('Ảnh có mặt trẻ em', $post->review_note);
        $this->assertStringContainsString('Ảnh có mặt trẻ em', $author->notifications()->sole()->data['body']);

        // Tác giả thấy lý do, sửa và gửi lại → quản trị viên duyệt → công khai, tác giả nhận thông báo
        $this->flushSession();
        $this->actingAs($author)->get('/admin/posts')->assertSee('Chưa được duyệt: Ảnh có mặt trẻ em');
        $this->actingAs($author)->postJson("/admin/posts/{$post->id}/submit")->assertOk();
        $this->assertNull($post->fresh()->review_note);
        $this->flushSession();
        $this->actingAs($admin)->postJson("/admin/posts/{$post->id}/publish")->assertOk();
        $this->assertTrue($post->fresh()->isPublished());
        $this->assertSame($admin->id, $post->fresh()->reviewed_by);
        $this->assertContains('Bài đã được duyệt và đăng: “Phát quà Trung thu”', $author->notifications()->get()->pluck('data.title'));
        auth()->guard('web')->logout();
        $this->get("/bai-viet/{$post->slug}")->assertOk()->assertSee('Phát quà Trung thu');
    }

    public function test_author_editing_published_post_sends_it_back_to_review(): void
    {
        $author = User::factory()->coordinator()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id, 'title' => 'Bài đang đăng']);

        $this->actingAs($author)->get("/admin/posts/{$post->id}/edit")->assertSee('tạm ẩn khỏi trang công khai');
        $this->actingAs($author)->putJson("/admin/posts/{$post->id}", ['title' => 'Bài đang đăng (sửa)', 'body' => 'Nội dung mới chưa duyệt'])
            ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'chờ quản trị viên duyệt lại'));

        $this->assertSame(PostStatus::Pending, $post->fresh()->status);
        auth()->guard('web')->logout();
        $this->get("/bai-viet/{$post->slug}")->assertNotFound(); // nội dung chưa duyệt không lên trang công khai
    }

    public function test_coordinator_only_manages_own_posts(): void
    {
        $me = User::factory()->coordinator()->create();
        $mine = Post::factory()->create(['author_id' => $me->id, 'title' => 'Bài của tôi']);
        $theirs = Post::factory()->published()->create(['title' => 'Bài người khác']);

        $this->actingAs($me)->get('/admin/posts')->assertSee('Bài của tôi')->assertDontSee('Bài người khác');
        $this->actingAs($me)->get("/admin/posts/{$theirs->id}/edit")->assertNotFound();
        $this->actingAs($me)->putJson("/admin/posts/{$theirs->id}", ['title' => 'X', 'body' => 'Y'])->assertNotFound();
        $this->actingAs($me)->deleteJson("/admin/posts/{$theirs->id}")->assertNotFound();
        $this->actingAs($me)->postJson("/admin/posts/{$theirs->id}/unpublish")->assertNotFound();
        $this->assertTrue($theirs->fresh()->isPublished());

        // Không tự duyệt được bài của mình
        $this->actingAs($me)->postJson("/admin/posts/{$mine->id}/publish")->assertForbidden();
        $this->actingAs($me)->postJson("/admin/posts/{$mine->id}/submit")->assertOk();
        $this->actingAs($me)->postJson("/admin/posts/{$mine->id}/reject", ['review_note' => 'x'])->assertForbidden();

        // Rút yêu cầu → về nháp; tự gỡ bài đang đăng của mình được
        $this->actingAs($me)->postJson("/admin/posts/{$mine->id}/withdraw")->assertOk();
        $this->assertSame(PostStatus::Draft, $mine->fresh()->status);
        $mine->forceFill(['status' => PostStatus::Published, 'published_at' => now()])->save();
        $this->actingAs($me)->postJson("/admin/posts/{$mine->id}/unpublish")->assertOk();
        $this->assertSame(PostStatus::Draft, $mine->fresh()->status);
    }

    public function test_admin_sees_all_and_publishes_own_post_directly(): void
    {
        $admin = User::factory()->admin()->create();
        Post::factory()->create(['title' => 'Bài của điều phối viên']);

        $this->actingAs($admin)->get('/admin/posts')->assertSee('Bài của điều phối viên');
        $this->actingAs($admin)->postJson('/admin/posts', ['title' => 'Thông báo chung', 'body' => 'Nội dung', 'save_as' => 'published'])
            ->assertOk();
        $this->assertTrue(Post::firstWhere('title', 'Thông báo chung')->isPublished());
        $this->assertSame(0, $admin->notifications()->count()); // không tự báo cho mình
    }
}
