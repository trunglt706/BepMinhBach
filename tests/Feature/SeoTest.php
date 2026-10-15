<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO: trang công khai được lập chỉ mục với thẻ đầy đủ; khu quản lý, đăng nhập, trang lỗi thì không. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_and_error_pages_are_not_indexed(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // Chưa đăng nhập: bị chuyển về /login — phản hồi chuyển hướng cũng gắn noindex
        $this->get('/admin')->assertRedirect('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->actingAs(User::factory()->coordinator()->create())->get('/admin')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/bai-viet/khong-ton-tai')->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString("Disallow: /admin\n", $robots);
        $this->assertStringContainsString("Disallow: /login\n", $robots);
    }

    public function test_public_pages_have_open_graph_and_are_indexable(): void
    {
        $home = $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $home->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:url" content="'.route('blog.index').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertDontSee(route('login'), false); // trang công khai không có nút đăng nhập

        $this->get('/lien-he')->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('<meta property="og:title" content="Liên hệ">', false)
            ->assertSee('<meta property="og:url" content="'.route('contact.create').'">', false);

        // Trang tìm kiếm: noindex, không có dữ liệu có cấu trúc của trang chủ
        $this->get('/?q=abc')->assertSee('<meta name="robots" content="noindex, follow">', false)->assertDontSee('"@type":"WebSite"', false);
    }

    public function test_post_page_has_single_set_of_article_tags(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Bài SEO']);

        $html = $this->get("/bai-viet/{$post->slug}")->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'property="og:type"'));
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertSame(1, substr_count($html, 'property="og:title"'));
        $this->assertSame(1, substr_count($html, 'name="twitter:card"'));
        $this->assertStringContainsString('"@type":"BlogPosting"', $html);
    }

    public function test_brand_is_gop_gio_everywhere(): void
    {
        foreach (['/', '/lien-he', '/login', '/forgot-password', '/khong-ton-tai'] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertStringContainsString('Góp', $html, $url);
            $this->assertDoesNotMatchRegularExpression('/Bếp\s?Minh\s?Bạch|bi-egg-fried/u', $html, $url);
        }
        $this->get('/')->assertSee('<meta property="og:site_name" content="Góp Gió">', false)
            ->assertSee('<span class="brand-accent">Gió</span>', false);
    }

    public function test_public_layout_has_back_to_top_button(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-back-to-top', false)
            ->assertSee('aria-label="Lên đầu trang"', false);
        $this->get('/lien-he')->assertOk()->assertSee('class="topic-options"', false);
    }
}
