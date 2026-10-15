<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\SponsorType;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Đơn vị tài trợ / hỗ trợ: quản lý, gắn vào chương trình, trang công khai. */
class SponsorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
    }

    public function test_coordinator_creates_and_edits_sponsor_with_logo_and_only_admin_deletes(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->post('/admin/sponsors', [
            'name' => 'Siêu thị Xanh', 'type' => 'in_kind', 'description' => "Tặng rau sạch.\nMỗi tuần.",
            'website' => 'sieuthixanh.vn', 'is_active' => '1', 'logo' => UploadedFile::fake()->image('logo.png', 600, 300),
        ])->assertRedirect('/admin/sponsors');

        $sponsor = Sponsor::sole();
        $this->assertSame('sieu-thi-xanh', $sponsor->slug);
        $this->assertSame(SponsorType::InKind, $sponsor->type);
        $this->assertSame('https://sieuthixanh.vn', $sponsor->website);
        $this->assertNotNull($sponsor->logo());
        $this->assertNotEmpty(Storage::disk('uploads')->allFiles('sponsors'));

        // Link nguy hiểm bị từ chối; đổi tên giữ nguyên đường dẫn
        $this->actingAs($coordinator)->put("/admin/sponsors/{$sponsor->slug}", ['name' => 'X', 'type' => 'media', 'website' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('website');
        $this->actingAs($coordinator)->put("/admin/sponsors/{$sponsor->slug}", ['name' => 'Siêu thị Xanh Mới', 'type' => 'in_kind', 'is_active' => '1', 'remove_logo' => '1'])
            ->assertRedirect();
        $sponsor->refresh();
        $this->assertSame('sieu-thi-xanh', $sponsor->slug);
        $this->assertNull($sponsor->logo());
        $this->assertSame([], Storage::disk('uploads')->allFiles('sponsors')); // logo cũ đã xóa

        $this->actingAs($coordinator)->delete("/admin/sponsors/{$sponsor->slug}")->assertForbidden();
        $this->actingAs(User::factory()->volunteer()->create())->get('/admin/sponsors')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete("/admin/sponsors/{$sponsor->slug}")->assertRedirect();
        $this->assertModelMissing($sponsor);
    }

    public function test_coordinator_links_sponsors_to_own_program_only(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $mine = CookingBatch::factory()->create(['coordinator_id' => $coordinator->id, 'created_by' => $coordinator->id]);
        $others = CookingBatch::factory()->create();
        $sponsor = Sponsor::factory()->create(['name' => 'Công ty Ánh Dương', 'type' => SponsorType::Sponsor]);

        $this->actingAs($coordinator)->get("/admin/batches/{$mine->id}/sponsors/create")->assertOk()->assertSee('Công ty Ánh Dương');
        $this->actingAs($coordinator)->post("/admin/batches/{$mine->id}/sponsors", ['sponsor_id' => $sponsor->id, 'contribution' => '500 phần quà'])
            ->assertRedirect(route('batches.show', $mine).'#don-vi');
        $this->assertSame('500 phần quà', $mine->sponsors()->sole()->pivot->contribution);
        $this->assertDatabaseHas('activity_logs', ['cooking_batch_id' => $mine->id, 'summary' => 'Thêm nhà tài trợ “Công ty Ánh Dương”.']);

        // Trùng → lỗi; chương trình ngoài phạm vi → 404
        $this->actingAs($coordinator)->post("/admin/batches/{$mine->id}/sponsors", ['sponsor_id' => $sponsor->id])->assertSessionHasErrors('sponsor_id');
        $this->actingAs($coordinator)->post("/admin/batches/{$others->id}/sponsors", ['sponsor_id' => $sponsor->id])->assertNotFound();

        $this->actingAs($coordinator)->get("/admin/batches/{$mine->id}")->assertSee('Đơn vị tham gia')->assertSee('500 phần quà');

        $this->actingAs($coordinator)->delete("/admin/batches/{$mine->id}/sponsors/{$sponsor->id}")->assertRedirect();
        $this->assertSame(0, $mine->sponsors()->count());
        $this->assertModelExists($sponsor); // gỡ khỏi chương trình không xóa đơn vị
    }

    public function test_public_pages_show_sponsors_by_type_and_only_public_programs(): void
    {
        $public = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['name' => 'Phát quà Trung thu']);
        $hidden = CookingBatch::factory()->status(BatchStatus::Planned)->create(['name' => 'Chương trình nội bộ']);
        $sponsor = Sponsor::factory()->create(['name' => 'Nhà sách Bình Minh', 'type' => SponsorType::Partner, 'description' => 'Đồng hành từ 2024.']);
        $media = Sponsor::factory()->create(['name' => 'Báo Tuổi Xanh', 'type' => SponsorType::Media]);
        $inactive = Sponsor::factory()->create(['name' => 'Đơn vị đang ẩn', 'is_active' => false]);
        foreach ([$sponsor, $media, $inactive] as $s) {
            $public->sponsors()->attach($s->id, ['contribution' => $s === $sponsor ? 'Tặng 300 cuốn sách' : null]);
        }
        $hidden->sponsors()->attach($sponsor->id);

        // Danh sách theo phân loại
        $this->get('/don-vi-dong-hanh')->assertOk()
            ->assertSeeInOrder(['Đơn vị đồng hành', 'Nhà sách Bình Minh', 'Truyền thông', 'Báo Tuổi Xanh'])
            ->assertDontSee('Đơn vị đang ẩn')
            ->assertSee('1 chương trình'); // không đếm chương trình chưa công khai

        // Trang riêng: chỉ chương trình công khai
        $this->get("/don-vi-dong-hanh/{$sponsor->slug}")->assertOk()
            ->assertSee('Nhà sách Bình Minh')->assertSee('Đồng hành từ 2024.')->assertSee('Đơn vị đồng hành')
            ->assertSee('Phát quà Trung thu')->assertSee('Tặng 300 cuốn sách')
            ->assertDontSee('Chương trình nội bộ');
        $this->get("/don-vi-dong-hanh/{$inactive->slug}")->assertNotFound();

        // Trang chương trình: đơn vị tham gia theo phân loại, có link tới trang đơn vị
        $this->get("/chuong-trinh/{$public->public_id}")->assertOk()
            ->assertSee('Đơn vị tham gia')
            ->assertSeeInOrder(['Đơn vị đồng hành', 'Nhà sách Bình Minh', 'Truyền thông', 'Báo Tuổi Xanh'])
            ->assertSee(route('sponsors.public.show', $sponsor), false)
            ->assertDontSee('Đơn vị đang ẩn');

        $this->get('/sitemap.xml')->assertSee(route('sponsors.public.show', $sponsor), false)
            ->assertDontSee(route('sponsors.public.show', $inactive), false);
        $this->get('/')->assertSee(route('sponsors.public.index'), false); // menu công khai
    }

    public function test_public_program_page_refreshes_after_linking_sponsor(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = CookingBatch::factory()->status(BatchStatus::Completed)->create();
        Post::factory()->published()->create(['cooking_batch_id' => $batch->id]);
        $sponsor = Sponsor::factory()->create(['name' => 'Đơn vị mới thêm']);

        $this->get("/chuong-trinh/{$batch->public_id}")->assertOk()->assertDontSee('Đơn vị mới thêm'); // đã lưu đệm
        // Chương trình đã hoàn tất vẫn thêm được đơn vị (cảm ơn sau chương trình)
        $this->actingAs($admin)->post("/admin/batches/{$batch->id}/sponsors", ['sponsor_id' => $sponsor->id])->assertRedirect();
        auth()->guard('web')->logout();
        $this->get("/chuong-trinh/{$batch->public_id}")->assertSee('Đơn vị mới thêm');
    }

    public function test_description_uses_rich_editor_and_is_sanitized(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->get('/admin/sponsors/create')->assertOk()
            ->assertSee('id="f_description" name="description" rows="6" data-rich-editor', false);

        // HTML từ trình soạn thảo: giữ định dạng an toàn, bỏ script/thuộc tính sự kiện/link javascript:
        $this->actingAs($coordinator)->post('/admin/sponsors', [
            'name' => 'Quỹ Mầm Xanh', 'type' => 'sponsor', 'is_active' => '1',
            'description' => '<div><strong>Đồng hành</strong> từ 2020<script>alert(1)</script></div>'
                .'<ul><li onclick="x()">Học bổng</li></ul><a href="javascript:alert(1)">xấu</a> <a href="https://mamxanh.vn">web</a>',
        ])->assertRedirect();
        $html = Sponsor::sole()->description;
        $this->assertStringContainsString('<strong>Đồng hành</strong>', $html);
        $this->assertStringContainsString('<li>Học bổng</li>', $html);
        $this->assertStringContainsString('href="https://mamxanh.vn"', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        // Trang công khai in HTML đã lọc; mô tả SEO là chữ thuần
        $sponsor = Sponsor::sole();
        $this->get("/don-vi-dong-hanh/{$sponsor->slug}")->assertOk()
            ->assertSee('<strong>Đồng hành</strong>', false)
            ->assertSee('<meta name="description" content="Đồng hành từ 2020 Học bổng xấu web">', false);

        // Chữ thuần (không JS) giữ xuống dòng và được escape; editor trống → null
        $sponsor->update(['description' => "Dòng 1 <b>\nDòng 2"]);
        $this->assertSame('<div>Dòng 1 &lt;b&gt;</div><div>Dòng 2</div>', $sponsor->fresh()->description);
        $sponsor->update(['description' => '<div><br></div>']);
        $this->assertNull($sponsor->fresh()->description);
    }

    public function test_migration_converts_old_plain_descriptions(): void
    {
        $id = DB::table('sponsors')->insertGetId([
            'name' => 'Cũ', 'slug' => 'cu', 'type' => 'sponsor', 'is_active' => true,
            'description' => "Dòng một & <hai>\n\nDòng ba", 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_10_15_000400_convert_sponsor_descriptions_to_html.php');
        $migration->up();
        $this->assertSame('<div>Dòng một &amp; &lt;hai&gt;</div><div><br></div><div>Dòng ba</div>', DB::table('sponsors')->where('id', $id)->value('description'));
    }

    /** Lỗi 15/10/2026: danh bạ trống → form "Thêm đơn vị tham gia" lỗi 500 (thiếu khối nút). */
    public function test_add_form_works_with_empty_directory_and_creates_new_sponsor_inline(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $batch = CookingBatch::factory()->create(['coordinator_id' => $coordinator->id, 'created_by' => $coordinator->id]);

        foreach (["/admin/batches/{$batch->id}/sponsors/create", "/admin/batches/{$batch->id}/sponsors/create?modal=1"] as $url) {
            $this->actingAs($coordinator)->get($url)->assertOk()
                ->assertSee('Thêm đơn vị mới')->assertSee('name="new_name"', false)->assertSee('Thêm vào chương trình');
        }

        // Tạo nhanh đơn vị mới và gắn luôn vào chương trình
        $this->actingAs($coordinator)->post("/admin/batches/{$batch->id}/sponsors", [
            'new_name' => '  Bếp chay Hoa Sen ', 'new_type' => 'in_kind', 'contribution' => '100 kg gạo',
        ])->assertRedirect(route('batches.show', $batch).'#don-vi');
        $sponsor = Sponsor::sole();
        $this->assertSame('Bếp chay Hoa Sen', $sponsor->name);
        $this->assertSame(SponsorType::InKind, $sponsor->type);
        $this->assertTrue($sponsor->is_active);
        $this->assertSame($coordinator->id, $sponsor->created_by);
        $this->assertSame('100 kg gạo', $batch->sponsors()->sole()->pivot->contribution);

        // Không chọn gì / làm cả hai / trùng tên → báo lỗi rõ ràng, không tạo bản trùng
        $other = CookingBatch::factory()->create(['coordinator_id' => $coordinator->id, 'created_by' => $coordinator->id]);
        $this->actingAs($coordinator)->post("/admin/batches/{$other->id}/sponsors", [])->assertSessionHasErrors('sponsor_id');
        $this->actingAs($coordinator)->post("/admin/batches/{$other->id}/sponsors", ['sponsor_id' => $sponsor->id, 'new_name' => 'X', 'new_type' => 'media'])
            ->assertSessionHasErrors('sponsor_id');
        $this->actingAs($coordinator)->post("/admin/batches/{$other->id}/sponsors", ['new_name' => 'bếp chay hoa sen', 'new_type' => 'sponsor'])
            ->assertSessionHasErrors('new_name');
        $this->actingAs($coordinator)->post("/admin/batches/{$other->id}/sponsors", ['new_name' => 'Đơn vị Y'])->assertSessionHasErrors('new_type');
        $this->assertSame(1, Sponsor::count());

        // Đã có đơn vị: form vẫn cho chọn có sẵn và tạo mới
        $this->actingAs($coordinator)->get("/admin/batches/{$other->id}/sponsors/create")->assertOk()
            ->assertSee('Chọn đơn vị có sẵn')->assertSee('Bếp chay Hoa Sen')->assertSee('Hoặc thêm đơn vị mới');
    }
}
