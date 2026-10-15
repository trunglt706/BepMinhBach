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
}
