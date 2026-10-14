<?php

namespace Tests\Feature;

use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Trang chủ "/" là danh sách bài viết, trang nội bộ nằm dưới /admin; ảnh bài viết tải lười. */
class PublicHomeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Gắn ảnh (chỉ bản ghi, không cần file) cho bài viết.
     *
     * @param  Post  $post  bài viết
     * @param  string  $kind  PostImage::COVER hoặc PostImage::GALLERY
     */
    private function image(Post $post, string $kind, string $path): PostImage
    {
        return $post->images()->create(['kind' => $kind, 'path' => $path, 'widths' => [480, 960, 1600], 'width' => 1600, 'height' => 900]);
    }

    public function test_home_is_the_public_post_list_for_guests_and_signed_in_users(): void
    {
        Post::factory()->published()->create(['title' => 'Bài trên trang chủ']);

        $this->get('/')->assertOk()->assertSee('Bài trên trang chủ');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()->assertSee('Bài trên trang chủ');
    }

    public function test_old_post_list_url_redirects_permanently_and_keeps_filters(): void
    {
        $this->get('/bai-viet')->assertStatus(301)->assertRedirect(route('blog.index'));
        $this->get('/bai-viet?tag=kinh-nghiem&q=rau')->assertStatus(301)
            ->assertRedirect(route('blog.index', ['tag' => 'kinh-nghiem', 'q' => 'rau']));
    }

    public function test_admin_slugs_are_english_and_old_vietnamese_paths_redirect(): void
    {
        $user = User::factory()->coordinator()->create();

        $this->assertSame(url('/admin/my-tasks'), route('my-tasks'));
        $this->assertSame(url('/admin/account/password'), route('account.password'));
        $this->assertSame(url('/admin/contacts'), route('contacts.index'));
        $this->actingAs($user)->get('/admin/cong-viec-cua-toi')->assertStatus(301)->assertRedirect('/admin/my-tasks');
        $this->actingAs($user)->get('/admin/tai-khoan/mat-khau')->assertStatus(301)->assertRedirect('/admin/account/password');
        $this->actingAs($user)->get('/admin/lien-he')->assertStatus(301)->assertRedirect('/admin/contacts');

        // Mọi đường dẫn trang quản lý chỉ dùng chữ tiếng Anh không dấu (trừ các đường dẫn cũ chỉ để chuyển hướng)
        $vietnamese = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin/'))
            ->reject(fn ($r) => str_contains((string) ($r->getAction()['controller'] ?? ''), 'RedirectController'))
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => preg_match('/cong-viec|tai-khoan|mat-khau|lien-he|xu-ly|bai-viet|dot-nau/', $uri))
            ->values()->all();
        $this->assertSame([], $vietnamese);
    }

    public function test_internal_pages_live_under_admin_prefix(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->assertSee('Xin chào');
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('dashboard'));
        $this->assertSame(url('/admin'), route('dashboard'));

        $outside = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('auth', $route->gatherMiddleware(), true))
            ->reject(fn ($route) => str_starts_with($route->uri(), 'admin'))
            ->map(fn ($route) => $route->uri())
            ->values()->all();
        $this->assertSame([], $outside, 'Trang cần đăng nhập phải nằm dưới /admin');
    }

    public function test_only_the_first_card_image_loads_eagerly_others_wait_for_viewport(): void
    {
        foreach (range(1, 3) as $i) {
            $post = Post::factory()->published()->create(['published_at' => now()->subDays($i)]);
            $this->image($post, PostImage::COVER, "posts/2026/10/cover{$i}");
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        $this->assertStringContainsString('src="'.asset('uploads/posts/2026/10/cover1-480.webp').'"', $html);
        // Ảnh 2, 3: chưa có src thật, chỉ tải khi vào khung nhìn; có bản <noscript> cho máy tìm kiếm/không JS
        $this->assertSame(2, substr_count($html, 'data-lazy data-src='));
        $this->assertSame(2, substr_count($html, '<noscript><img src='));
        $this->assertDoesNotMatchRegularExpression('/<img(?![^>]*data-src)[^>]*src="[^"]*cover2-480/', str_replace(['<noscript><img'], ['<noscript-img'], $html));
        $this->assertStringContainsString('img[data-lazy]{display:none!important}', $html);
    }

    public function test_second_page_has_no_priority_image(): void
    {
        foreach (range(1, 10) as $i) {
            $post = Post::factory()->published()->create(['published_at' => now()->subDays($i)]);
            $this->image($post, PostImage::COVER, "posts/2026/10/p{$i}");
        }

        $this->assertStringNotContainsString('fetchpriority="high"', $this->get('/?page=2')->assertOk()->getContent());
    }

    public function test_home_has_header_search_six_posts_per_page_author_and_batch_badge(): void
    {
        $author = User::factory()->coordinator()->create(['name' => 'Chị Điều Phối']);
        $batch = CookingBatch::factory()->create(['name' => 'Cơm chay chủ nhật']);
        foreach (range(1, 7) as $i) {
            Post::factory()->published()->create([
                'title' => "Bài {$i}", 'author_id' => $author->id, 'cooking_batch_id' => $i === 1 ? $batch->id : null,
                'published_at' => now()->subHours($i),
            ]);
        }

        $html = $this->get('/')->assertOk()
            ->assertSee('class="header-search', false)->assertSee('id="hs_q"', false)
            ->assertDontSee('id="b_q"', false)->assertDontSee('aria-label="Thẻ phổ biến"', false) // bỏ form tìm + danh sách thẻ ở thân trang
            ->assertSee('Bài 6')->assertDontSee('Bài 7')                                          // tối đa 6 bài/trang
            ->assertSee('Chị Điều Phối')
            ->assertSee('class="post-card-badge"', false)->assertSee('Cơm chay chủ nhật')
            ->assertSee(route('blog.batch', $batch))
            ->getContent();
        $this->assertStringContainsString('?page=2', $html); // có phân trang
        $this->get('/?page=2')->assertSee('Bài 7');

        // Ô tìm kiếm trên header có ở mọi trang công khai
        $this->get('/lien-he')->assertSee('id="hs_q"', false);
    }

    public function test_home_total_sits_beside_title_and_search_clears_by_submitting(): void
    {
        Post::factory()->published()->count(2)->create();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="home-header-row">\s*<h1 class="page-title mb-0">Bài viết<\/h1>\s*<span class="home-count"[^>]*>.*2 bài viết<\/span>/su', $html);
        $this->assertStringNotContainsString('role="status"', $html); // không lặp số bài khi không lọc
        $this->assertStringContainsString('id="hs_q" name="q" value="" maxlength="100" placeholder="Tìm bài viết…" autocomplete="off" data-submit-on-clear', $html);

        $this->get('/?q=xyz')->assertSee('Tìm thấy <strong>0</strong> bài viết', false)->assertSee('0 bài viết');
        $this->assertStringContainsString("document.addEventListener('search', submitOnClear, true);", (string) file_get_contents(public_path('js/app.js')));
    }

    public function test_post_detail_shows_author_and_view_count_rules(): void
    {
        $author = User::factory()->coordinator()->create(['name' => 'Anh Người Viết']);
        $post = Post::factory()->published()->create(['author_id' => $author->id]);
        $chrome = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36'];

        // Khách: thấy người viết; lần đầu +1, tải lại cùng máy không tăng, máy khác +1
        $this->withServerVariables($chrome + ['REMOTE_ADDR' => '203.0.113.7'])->get("/bai-viet/{$post->slug}")
            ->assertOk()->assertSee('Anh Người Viết')->assertSee('1 lượt xem')->assertDontSee('lượt xem của bạn không được tính');
        $this->withServerVariables($chrome + ['REMOTE_ADDR' => '203.0.113.7'])->get("/bai-viet/{$post->slug}")->assertSee('1 lượt xem');
        $this->withServerVariables($chrome + ['REMOTE_ADDR' => '198.51.100.9'])->get("/bai-viet/{$post->slug}")->assertSee('2 lượt xem');

        // Người quản lý bài: không tính, và được nhắc rõ lý do
        $this->actingAs($author)->withServerVariables($chrome + ['REMOTE_ADDR' => '192.0.2.50'])->get("/bai-viet/{$post->slug}")
            ->assertSee('2 lượt xem')->assertSee('lượt xem của bạn không được tính');
        $this->assertSame(2, $post->fresh()->view_count);
    }

    public function test_posts_without_cover_show_placeholder_background(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Bài chưa có ảnh']);
        $other = Post::factory()->published()->create();

        $this->get('/')->assertOk()
            ->assertSee('post-card-media is-placeholder', false)
            ->assertSee('post-card-placeholder', false);
        $this->get("/bai-viet/{$other->slug}")->assertOk()->assertSee('related-thumb is-empty', false);
    }

    public function test_post_detail_has_no_top_cover_but_keeps_share_image_and_lazy_album(): void
    {
        $batch = CookingBatch::factory()->create(['name' => 'Đợt có bài']);
        $post = Post::factory()->published()->create(['cooking_batch_id' => $batch->id]);
        $this->image($post, PostImage::COVER, 'posts/2026/10/cover');
        $this->image($post, PostImage::GALLERY, 'posts/2026/10/album1');

        $html = $this->get("/bai-viet/{$post->slug}")->assertOk()->getContent();

        // Không hiện ảnh đại diện ở đầu bài; ảnh vẫn dùng khi chia sẻ link (Open Graph)
        $this->assertStringNotContainsString('class="post-cover"', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*cover-(480|960|1600)\.webp/', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.asset('uploads/posts/2026/10/cover-1600.webp').'">', $html);
        // Breadcrumb đợt nấu dẫn tới URL mã hash của đợt
        $this->assertMatchesRegularExpression('#<li class="breadcrumb-item"><a href="'.preg_quote(route('blog.batch', $batch), '#').'">Đợt có bài</a></li>#u', $html);
        $this->assertStringNotContainsString('?dot=', $html);
        $this->assertStringContainsString('data-src="'.asset('uploads/posts/2026/10/album1-480.webp').'"', $html);
        $this->assertStringContainsString('bi-zoom-in', $html);
    }
}
