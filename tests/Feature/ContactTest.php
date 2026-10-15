<?php

namespace Tests\Feature;

use App\Enums\ContactTopic;
use App\Http\Requests\ContactRequest;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\Mask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Trang liên hệ công khai: gửi lời nhắn, 1 liên hệ / client / ngày, giới hạn tần suất, quản lý nội bộ. */
class ContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dữ liệu hợp lệ (có thể ghi đè từng trường).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'topic' => ContactTopic::Volunteer->value,
            'name' => 'Khách thử',
            'contact' => 'khach@example.org',
            'message' => 'Tôi có thể phụ sơ chế rau vào sáng thứ bảy.',
        ], $overrides);
    }

    public function test_contact_page_lists_suggested_topics_and_preselects_from_query(): void
    {
        $response = $this->get('/lien-he?chu-de=donation')->assertOk()->assertSee('Gửi liên hệ');

        foreach (ContactTopic::cases() as $topic) {
            $response->assertSee($topic->label());
        }
        $this->assertMatchesRegularExpression('/value="donation"[^>]*checked/', $response->getContent());
    }

    public function test_guest_can_send_contact_and_phone_is_normalized(): void
    {
        $this->post('/lien-he', $this->payload(['contact' => '0901 234.567', 'topic' => 'donation']))
            ->assertRedirect(route('contact.create'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '090****567') && ! str_contains($m, '0901234567'));

        $message = ContactMessage::sole();
        $this->assertSame('0901234567', $message->contact);
        $this->assertSame(ContactTopic::Donation, $message->topic);
        $this->assertSame(64, strlen($message->client_hash));
        $this->assertStringNotContainsString('127.0.0.1', $message->client_hash); // không lưu IP thô
    }

    public function test_validation_errors_and_honeypot(): void
    {
        $this->post('/lien-he', $this->payload(['contact' => 'không phải email', 'message' => 'ngắn', 'topic' => 'xin-suat-an']))
            ->assertSessionHasErrors(['contact', 'message', 'topic']);
        $this->post('/lien-he', $this->payload(['website' => 'http://spam.example']))->assertSessionHasErrors('website');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_one_contact_per_client_per_day(): void
    {
        $this->post('/lien-he', $this->payload())->assertSessionHasNoErrors();
        $this->get('/lien-he')->assertSee('Bạn đã gửi liên hệ hôm nay')->assertDontSee('name="message"', false);

        // Cùng IP, khác email → vẫn bị chặn trong ngày
        $this->post('/lien-he', $this->payload(['contact' => 'khac@example.org']))->assertSessionHasErrors('limit');

        // IP khác nhưng cùng email → cũng bị chặn
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->post('/lien-he', $this->payload())->assertSessionHasErrors('limit');
        // IP khác, email khác → được gửi
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->post('/lien-he', $this->payload(['contact' => 'nguoikhac@example.org']))->assertSessionHasNoErrors();

        // Sang ngày hôm sau gửi lại được
        $this->travel(1)->days();
        $this->post('/lien-he', $this->payload(['contact' => 'khac@example.org']))->assertSessionHasNoErrors();

        $this->assertSame(3, ContactMessage::count());
    }

    public function test_submissions_are_rate_limited_per_ip_including_failed_ones(): void
    {
        config(['bmb.rate_limits.contact' => 2]);
        RateLimiter::clear('contact');

        $this->post('/lien-he', $this->payload(['message' => '']))->assertSessionHasErrors('message');
        $this->post('/lien-he', $this->payload(['message' => '']))->assertSessionHasErrors('message');
        $this->post('/lien-he', $this->payload())->assertSessionHas('error');
        $this->postJson('/lien-he', $this->payload())->assertStatus(429);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_public_header_has_contact_link_and_no_admin_button_and_footer_is_copyright_only(): void
    {
        $guest = $this->get('/')->assertOk()
            ->assertSee(route('contact.create'))
            ->assertDontSee(route('login')) // trang công khai không có nút đăng nhập
            ->assertSee('© '.now()->year.' Góp Gió');
        $footer = str($guest->getContent())->after('<footer')->before('</footer>');
        $this->assertStringNotContainsString('kiểm toán', (string) $footer);

        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()
            ->assertDontSee('Trang quản lý')
            ->assertDontSee('>Quản lý<', false);
    }

    public function test_contact_form_has_client_side_validation_attributes(): void
    {
        $html = $this->get('/lien-he')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="name"[^>]*required|required[^>]*name="name"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="contact"[^>]*data-validate="email-or-phone"/', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="message"[^>]*minlength="10"/', $html);
        $this->assertMatchesRegularExpression('/<input type="radio" name="topic"[^>]*required/', $html);

        // Bộ kiểm tra phía trình duyệt chạy trước mọi xử lý gửi (pha capture) và có quy tắc email/SĐT như máy chủ.
        $js = (string) file_get_contents(public_path('js/app.js'));
        $this->assertStringContainsString("if (Object.keys(errors).length) {\n            event.preventDefault();\n            event.stopImmediatePropagation();", $js);
        $this->assertStringContainsString('var PHONE_RE = /^(?:\+84|0)\d{9,10}$/;', $js);
        $this->assertSame(ContactRequest::PHONE_PATTERN, '/^(?:\+84|0)\d{9,10}$/');
    }

    public function test_admin_can_search_contacts_by_name_email_or_phone(): void
    {
        ContactMessage::factory()->create(['name' => 'Nguyễn Thị Lan', 'contact' => 'lan@example.org']);
        ContactMessage::factory()->create(['name' => 'Trần Văn Minh', 'contact' => '0901234567']);
        ContactMessage::factory()->create(['name' => 'Lê Hoa', 'contact' => 'hoa_le@example.org']);
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->get('/admin/contacts?q=lan')->assertOk()
            ->assertSee('Nguyễn Thị Lan')->assertDontSee('Trần Văn Minh')->assertDontSee('Lê Hoa')
            ->assertSee('value="lan"', false);
        $this->actingAs($coordinator)->get('/admin/contacts?q=hoa_le%40')->assertSee('Lê Hoa')->assertDontSee('Nguyễn Thị Lan');
        // SĐT gõ có dấu cách vẫn tìm được
        $this->actingAs($coordinator)->get('/admin/contacts?q=0901+234')->assertSee('Trần Văn Minh')->assertDontSee('Lê Hoa');
        // "_" và "%" không là ký tự đại diện
        $this->actingAs($coordinator)->get('/admin/contacts?q=%25')->assertSee('Không tìm thấy liên hệ');
    }

    public function test_admin_sees_contact_statistics(): void
    {
        ContactMessage::factory()->count(2)->create(['topic' => ContactTopic::Donation]);
        ContactMessage::factory()->create(['topic' => ContactTopic::Volunteer, 'sent_on' => today()->subDays(3)]);
        ContactMessage::factory()->create(['topic' => ContactTopic::Feedback, 'sent_on' => today()->subDays(20)])
            ->forceFill(['handled_at' => now()])->save();
        $coordinator = User::factory()->coordinator()->create();

        $html = $this->actingAs($coordinator)->get('/admin/contacts?status=handled')->assertOk()->getContent();

        $stat = fn (string $label) => (string) str($html)->after($label)->before('</span>')->after('stat-value">')->trim();
        $this->assertStringContainsString('Thống kê liên hệ', $html);
        foreach (['Tổng liên hệ' => '4', 'Chưa xử lý' => '3', 'Đã xử lý' => '1', 'Hôm nay' => '2'] as $label => $value) {
            $this->assertMatchesRegularExpression('/'.preg_quote($label, '/').'\s*<\/span>\s*<span class="stat-value[^"]*">'.$value.'</u', $html, $label);
        }
        $this->assertStringContainsString('3 trong 7 ngày qua', $html);
        $this->assertStringContainsString(ContactTopic::Donation->label(), $html);
    }

    public function test_coordinator_manages_contacts_and_volunteer_cannot(): void
    {
        $message = ContactMessage::factory()->create(['name' => 'Chị Thử', 'message' => "Dòng 1\nDòng 2"]);
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->get('/admin/contacts')->assertOk()
            ->assertSee('Chị Thử')->assertSee(Mask::contact($message->contact))
            ->assertDontSee($message->contact) // danh sách chỉ có bản đã che
            ->assertSee('<span class="nav-badge" aria-label="1 liên hệ chưa xử lý">1</span>', false);

        $this->actingAs($coordinator)->postJson("/admin/contacts/{$message->id}/toggle")->assertOk();
        $this->assertNotNull($message->fresh()->handled_at);
        $this->assertSame($coordinator->id, $message->fresh()->handled_by);
        $this->actingAs($coordinator)->get('/admin/contacts')->assertSee('Không có liên hệ nào cần xử lý');
        $this->actingAs($coordinator)->get('/admin/contacts?status=handled')->assertSee('Chị Thử');
        $this->actingAs($coordinator)->get('/admin/contacts?status=khong-hop-le&topic=xyz')->assertOk();

        $volunteer = User::factory()->volunteer()->create();
        $this->actingAs($volunteer)->get('/admin/contacts')->assertForbidden();
        $this->actingAs($volunteer)->post("/admin/contacts/{$message->id}/toggle")->assertForbidden();
        $this->actingAs($volunteer)->delete("/admin/contacts/{$message->id}")->assertForbidden();
        $this->actingAs($volunteer)->get('/admin')->assertDontSee(route('contacts.index'));

        $this->actingAs($coordinator)->deleteJson("/admin/contacts/{$message->id}")->assertOk();
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_contact_info_is_masked_and_revealed_only_on_request_with_audit_log(): void
    {
        $this->assertSame('l*****@gmail.com', Mask::contact('lan.nguyen@gmail.com'));
        $this->assertSame('090****567', Mask::contact('0901234567'));
        $this->assertSame('', Mask::contact(null));

        $message = ContactMessage::factory()->create(['contact' => 'lan.nguyen@gmail.com']);
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->get('/admin/contacts')->assertOk()
            ->assertSee('l*****@gmail.com')->assertDontSee('lan.nguyen')
            ->assertSee(route('contacts.reveal', $message), false);

        $this->actingAs($coordinator)->postJson("/admin/contacts/{$message->id}/reveal")->assertOk()
            ->assertExactJson(['contact' => 'lan.nguyen@gmail.com', 'href' => 'mailto:lan.nguyen@gmail.com'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('activity_logs', ['user_id' => $coordinator->id, 'action' => 'contact.revealed',
            'summary' => 'Xem thông tin liên lạc của liên hệ #'.$message->id.'.']);
        $this->assertStringNotContainsString('lan.nguyen', ActivityLog::pluck('summary')->implode(' ')); // nhật ký không chứa email

        // Không có quyền xem liên hệ → không lấy được
        $this->actingAs(User::factory()->volunteer()->create())->postJson("/admin/contacts/{$message->id}/reveal")->assertForbidden();
        $this->get("/admin/contacts/{$message->id}/reveal")->assertStatus(405); // chỉ POST
    }
}
