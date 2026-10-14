<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Bài viết: nội dung định dạng (lọc HTML), ảnh WebP, giới hạn ảnh, lượt xem, SEO. */
class PostMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->coordinator = User::factory()->coordinator()->create();
    }

    /**
     * Gửi form tạo bài (AJAX) với dữ liệu mặc định hợp lệ.
     *
     * @param  array<string, mixed>  $data  dữ liệu ghi đè
     */
    private function storePost(array $data = []): TestResponse
    {
        return $this->actingAs($this->coordinator)->post('/posts', [
            'title' => 'Bài có ảnh', 'body' => '<div>Nội dung</div>', 'save_as' => 'published', ...$data,
        ], ['Accept' => 'application/json']);
    }

    /**
     * File WebP thật có chữ ký RIFF....WEBP.
     *
     * @param  string  $path  đường dẫn trên disk uploads
     */
    private function assertIsWebp(string $path): void
    {
        Storage::disk('uploads')->assertExists($path);
        $bytes = Storage::disk('uploads')->get($path);
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
    }

    public function test_rich_body_is_sanitized_and_plain_text_kept_for_search(): void
    {
        $this->storePost(['body' => '<h1>Tiêu đề</h1><div onclick="x()">Chào <strong>bạn</strong><script>alert(1)</script></div>'
            .'<img src=x onerror=alert(1)><iframe src="https://evil"></iframe><a href="javascript:alert(1)">xấu</a>'
            .'<a href="https://example.com" target="_blank" style="color:red">tốt</a><p style="color:red" class="x">P</p>'])->assertOk();

        $post = Post::firstOrFail();
        $this->assertStringContainsString('<h2>Tiêu đề</h2>', $post->body);
        $this->assertStringContainsString('<strong>bạn</strong>', $post->body);
        $this->assertStringContainsString('<a href="https://example.com" rel="nofollow noopener noreferrer ugc">tốt</a>', $post->body);
        foreach (['<script', 'onclick', 'onerror', '<img', '<iframe', 'javascript:', 'style=', 'class=', '<h1'] as $bad) {
            $this->assertStringNotContainsString($bad, $post->body);
        }
        // Hai liên kết nằm sát nhau trong HTML (không có khoảng trắng) → chữ cũng sát nhau; các khối được tách.
        $this->assertSame('Tiêu đề Chào bạn xấutốt P', $post->body_text);

        // Trang công khai in HTML đã lọc
        $this->get("/bai-viet/{$post->slug}")->assertOk()->assertSee('<strong>bạn</strong>', false)->assertDontSee('alert(1)', false);
    }

    public function test_body_that_is_empty_after_sanitizing_is_rejected(): void
    {
        $this->storePost(['body' => '<div><br></div><script>alert(1)</script>'])
            ->assertStatus(422)->assertJsonValidationErrors(['body' => 'Vui lòng nhập nội dung bài viết.']);
    }

    public function test_images_are_converted_to_webp_variants_without_keeping_original(): void
    {
        $this->storePost([
            'cover' => UploadedFile::fake()->image('anh-bia.jpg', 3000, 2000),
            'gallery' => [UploadedFile::fake()->image('a.png', 1200, 900), UploadedFile::fake()->image('b.jpg', 300, 200)],
        ])->assertOk();

        $post = Post::firstOrFail();
        $cover = $post->cover;
        $this->assertSame([480, 960, 1600], $cover->widths);
        $this->assertSame([3000, 2000], [$cover->width, $cover->height]);
        foreach ($cover->widths as $w) {
            $this->assertIsWebp("{$cover->path}-{$w}.webp");
        }
        $this->assertSame([480, 960, 1200], $post->gallery[0]->widths); // không phóng to quá kích thước gốc
        $this->assertSame([300], $post->gallery[1]->widths);

        // Chỉ có file WebP, tên ngẫu nhiên; không lưu file gốc hay tên gốc
        $files = Storage::disk('uploads')->allFiles();
        $this->assertCount(3 + 3 + 1, $files);
        foreach ($files as $file) {
            $this->assertStringEndsWith('.webp', $file);
            $this->assertStringNotContainsString('anh-bia', $file);
        }
        $this->assertStringContainsString('-480.webp 480w', $cover->srcset());
    }

    public function test_fake_and_unsupported_image_files_are_rejected(): void
    {
        $cases = [
            UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "x"; ?>'),          // đuôi .jpg, nội dung PHP
            UploadedFile::fake()->createWithContent('anh.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('tai-lieu.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->image('dong.gif'),
        ];
        foreach ($cases as $file) {
            $this->storePost(['cover' => $file])->assertStatus(422)->assertJsonValidationErrors('cover');
            $this->storePost(['gallery' => [$file]])->assertStatus(422)->assertJsonValidationErrors('gallery');
        }
        $this->assertSame(0, Post::count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_oversized_upload_is_rejected(): void
    {
        config(['bmb.post_images.max_upload_kb' => 50]);

        $this->storePost(['cover' => UploadedFile::fake()->image('to.jpg', 100, 100)->size(51)])
            ->assertStatus(422)->assertJsonValidationErrors(['cover' => 'Mỗi ảnh tối đa 0 MB.']);
    }

    public function test_album_limit_counts_existing_and_new_images(): void
    {
        config(['bmb.post_images.max_album' => 3]);

        $images = fn (int $n) => array_map(fn ($i) => UploadedFile::fake()->image("a{$i}.jpg", 50, 50), range(1, $n));
        $this->storePost(['gallery' => $images(4)])->assertStatus(422)->assertJsonValidationErrors('gallery');
        $this->storePost(['gallery' => $images(2)])->assertOk();

        $post = Post::firstOrFail();
        $update = fn (array $data) => $this->actingAs($this->coordinator)
            ->put("/posts/{$post->id}", ['title' => 'Bài', 'body' => 'Nội dung', ...$data], ['Accept' => 'application/json']);

        $update(['gallery' => $images(2)])->assertStatus(422)->assertJsonValidationErrors(['gallery' => 'Mỗi bài tối đa 3 ảnh album (đang có 2, thêm 2).']);
        // Xóa 1 ảnh cũ thì thêm được 2
        $update(['gallery' => $images(2), 'remove_images' => [$post->gallery[0]->id]])->assertOk();
        $this->assertSame(3, $post->gallery()->count());
    }

    public function test_removing_and_replacing_images_deletes_files_and_ignores_foreign_ids(): void
    {
        $this->storePost([
            'cover' => UploadedFile::fake()->image('c.jpg', 600, 400),
            'gallery' => [UploadedFile::fake()->image('g.jpg', 600, 400)],
        ])->assertOk();
        $post = Post::firstOrFail();
        $oldCover = $post->cover;
        $galleryImage = $post->gallery->first();

        $other = Post::factory()->create();
        $foreign = $other->images()->create(['kind' => PostImage::GALLERY, 'path' => 'posts/x/foreign', 'widths' => [480], 'width' => 480, 'height' => 320]);

        $this->actingAs($this->coordinator)->put("/posts/{$post->id}", [
            'title' => 'Bài', 'body' => 'Nội dung',
            'cover' => UploadedFile::fake()->image('moi.jpg', 800, 600),
            'remove_images' => [$galleryImage->id, $foreign->id],
            'captions' => [$foreign->id => 'Sửa trộm'],
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('uploads')->assertMissing("{$oldCover->path}-480.webp");
        Storage::disk('uploads')->assertMissing("{$galleryImage->path}-480.webp");
        $this->assertModelMissing($galleryImage);
        $this->assertNotSame($oldCover->path, $post->fresh()->cover->path);
        $this->assertModelExists($foreign);           // ảnh của bài khác không bị xóa
        $this->assertNull($foreign->fresh()->caption); // ... và không bị sửa chú thích

        // Xóa ảnh đại diện; xóa bài thì xóa hết file
        $this->actingAs($this->coordinator)->put("/posts/{$post->id}", ['title' => 'Bài', 'body' => 'Nội dung', 'remove_cover' => '1'],
            ['Accept' => 'application/json'])->assertOk();
        $this->assertNull($post->fresh()->cover);
        $this->actingAs($this->coordinator)->deleteJson("/posts/{$post->id}")->assertOk();
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_captions_are_saved_and_shown_in_album_with_lightbox_data(): void
    {
        $this->storePost(['gallery' => [UploadedFile::fake()->image('g.jpg', 2000, 1000)]])->assertOk();
        $post = Post::firstOrFail();
        $image = $post->gallery->first();

        $this->actingAs($this->coordinator)->put("/posts/{$post->id}", [
            'title' => 'Bài', 'body' => 'Nội dung', 'captions' => [$image->id => '  Nấu cơm  '],
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('Nấu cơm', $image->fresh()->caption);

        auth()->guard('web')->logout();
        $this->get("/bai-viet/{$post->slug}")->assertOk()
            ->assertSee('data-gallery', false)
            ->assertSee('data-pswp-width="1600" data-pswp-height="800"', false)
            ->assertSee('<figcaption>Nấu cơm</figcaption>', false)
            ->assertSee('loading="lazy"', false);
    }

    public function test_view_count_is_counted_once_per_visitor_and_skips_bots_and_managers(): void
    {
        $post = Post::factory()->published()->create();
        $visit = fn (string $agent, string $ip = '10.0.0.1') => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', $agent)->get("/bai-viet/{$post->slug}")->assertOk();

        $visit('Mozilla/5.0 (iPhone)');
        $visit('Mozilla/5.0 (iPhone)');            // tải lại trang: không tính thêm
        $this->assertSame(1, $post->fresh()->view_count);

        $visit('Mozilla/5.0 (Windows)');           // trình duyệt khác
        $visit('Mozilla/5.0 (iPhone)', '10.0.0.2'); // người khác
        $visit('Googlebot/2.1 (+http://www.google.com/bot.html)');
        $visit('curl/8.0');
        $this->assertSame(3, $post->fresh()->view_count);

        // Người quản lý xem bài không làm tăng lượt xem; updated_at của bài không đổi khi đếm.
        $updatedAt = $post->fresh()->updated_at;
        $this->actingAs($this->coordinator)->withHeader('User-Agent', 'Mozilla/5.0 (Mac)')->get("/bai-viet/{$post->slug}")->assertOk();
        $this->assertSame(3, $post->fresh()->view_count);
        $this->assertTrue($updatedAt->equalTo($post->fresh()->updated_at));

        // Hết khoảng thời gian → tính lại
        $this->travel(config('bmb.post_view_window_minutes') + 1)->minutes();
        auth()->guard('web')->logout();
        $visit('Mozilla/5.0 (iPhone)');
        $this->assertSame(4, $post->fresh()->view_count);

        // Bản nháp xem trước không được tính
        $draft = Post::factory()->create();
        $this->actingAs($this->coordinator)->get("/bai-viet/{$draft->slug}")->assertOk();
        $this->assertSame(0, $draft->fresh()->view_count);
    }

    public function test_seo_metadata_structured_data_and_sitemap(): void
    {
        $this->storePost(['title' => 'Cơm chay CN', 'excerpt' => 'Tóm tắt ngắn', 'cover' => UploadedFile::fake()->image('c.jpg', 2000, 1000), 'tags' => 'cơm chay'])->assertOk();
        $post = Post::firstOrFail();
        $draft = Post::factory()->create(['title' => 'Nháp không lên sitemap']);
        auth()->guard('web')->logout();

        $html = $this->get("/bai-viet/{$post->slug}")->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.route('blog.show', $post).'">', $html);
        $this->assertStringContainsString('<meta name="description" content="Tóm tắt ngắn">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow, max-image-preview:large">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.$post->cover->url().'">', $html);
        $this->assertStringContainsString('<meta property="article:tag" content="cơm chay">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertMatchesRegularExpression('#<script type="application/ld\+json">\{.*"@type":"BlogPosting".*\}</script>#', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringNotContainsString($this->coordinator->name, $html); // không nêu tên tác giả

        // Trang tìm kiếm/lọc: noindex; danh sách gốc: index + canonical
        $this->get('/bai-viet?q=c%C6%A1m')->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/bai-viet')->assertSee('<link rel="canonical" href="'.route('blog.index').'">', false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('blog.show', $post).'</loc>', false)
            ->assertDontSee($draft->slug);
    }

    public function test_post_form_renders_editor_image_and_tag_inputs(): void
    {
        $this->actingAs($this->coordinator)->get('/posts/create?modal=1')->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('data-rich-editor', false)
            ->assertSee('name="cover" accept="image/jpeg,image/png,image/webp" data-image-input', false)
            ->assertSee('name="gallery[]"', false)
            ->assertSee('data-tags-input data-max-tags="10"', false)
            ->assertSee('<datalist id="tag-suggestions">', false);
    }

    public function test_activity_log_redacts_contact_details_but_keeps_amounts(): void
    {
        $log = ActivityLog::record(null, $this->coordinator, ActivityAction::PostUpdated,
            'Liên hệ a.b@vidu.vn hoặc 0912 345 678 / +84912345678. Chi 1.250.000 đ, 150 suất.');

        $this->assertSame('Liên hệ [email] hoặc [số điện thoại] / [số điện thoại]. Chi 1.250.000 đ, 150 suất.', $log->summary);
        $this->assertSame(ActivityAction::PostUpdated, $log->fresh()->action);
    }
}
