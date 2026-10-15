<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\ContactTopic;
use App\Http\Requests\ContactIndexRequest;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Quản lý liên hệ khách gửi (điều phối viên trở lên): xem, đánh dấu đã xử lý, xóa. */
class ContactMessageController extends Controller
{
    /**
     * Danh sách liên hệ (mặc định chưa xử lý, mới nhất trước), tìm theo tên/email/SĐT, kèm thống kê tổng quan.
     *
     * @param  ContactIndexRequest  $request  bộ lọc đã chuẩn hóa (đã kiểm tra quyền)
     */
    public function index(ContactIndexRequest $request): View
    {
        $state = $request->state();
        $topic = $request->topic();
        $q = $request->keyword();

        $messages = ContactMessage::query()
            ->with('handler:id,name')
            // Tìm theo tên hoặc email/SĐT. Số điện thoại lưu không dấu cách → bỏ ký tự phân cách của từ khóa khi so SĐT.
            ->when($q !== '', function ($query) use ($q) {
                $digits = (string) preg_replace('/[\s.\-()]/', '', $q);
                $query->where(fn ($w) => $w->whereRaw("name like ? escape '!'", [self::like($q)])
                    ->orWhereRaw("contact like ? escape '!'", [self::like($q)])
                    ->when($digits !== $q && $digits !== '', fn ($w) => $w->orWhereRaw("contact like ? escape '!'", [self::like($digits)])));
            })
            ->when($state === ContactIndexRequest::STATE_NEW, fn ($q) => $q->whereNull('handled_at'))
            ->when($state === ContactIndexRequest::STATE_HANDLED, fn ($q) => $q->whereNotNull('handled_at'))
            ->when($topic, fn ($q) => $q->where('topic', $topic->value))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('contacts.index', [
            'messages' => $messages,
            'q' => $q,
            'state' => $state,
            'topic' => $topic,
            'topics' => ContactTopic::cases(),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Mẫu LIKE "chứa từ khóa", coi % và _ là ký tự thường. Dùng ký tự thoát "!" (khai báo bằng ESCAPE)
     * để chạy giống nhau trên MySQL/MariaDB và SQLite.
     *
     * @param  string  $value  từ khóa người dùng nhập
     */
    private static function like(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }

    /**
     * Thống kê toàn bộ liên hệ (không phụ thuộc bộ lọc): tổng, chưa/đã xử lý, hôm nay, 7 ngày qua, theo chủ đề.
     *
     * @return array{total: int, new: int, handled: int, today: int, week: int, byTopic: list<array{topic: ContactTopic, count: int}>}
     */
    private function stats(): array
    {
        $row = ContactMessage::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when handled_at is null then 1 else 0 end) as new_count')
            // So theo khoảng (không dùng "="): cột ngày có thể lưu kèm 00:00:00 tùy hệ CSDL.
            ->selectRaw('sum(case when sent_on >= ? and sent_on < ? then 1 else 0 end) as today_count', [today()->toDateString(), today()->addDay()->toDateString()])
            ->selectRaw('sum(case when sent_on >= ? then 1 else 0 end) as week_count', [today()->subDays(6)->toDateString()])
            ->first();

        $byTopic = ContactMessage::query()->selectRaw('topic, count(*) as aggregate')->groupBy('topic')->pluck('aggregate', 'topic');

        $total = (int) $row->total;
        $new = (int) $row->new_count;

        return [
            'total' => $total,
            'new' => $new,
            'handled' => $total - $new,
            'today' => (int) $row->today_count,
            'week' => (int) $row->week_count,
            'byTopic' => array_map(fn (ContactTopic $t) => ['topic' => $t, 'count' => (int) ($byTopic[$t->value] ?? 0)], ContactTopic::cases()),
        ];
    }

    /**
     * Đánh dấu đã xử lý (hoặc bỏ đánh dấu).
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  ContactMessage  $contact  liên hệ
     */
    public function toggle(Request $request, ContactMessage $contact): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $contact);

        $handled = $contact->handled_at === null;
        $contact->forceFill([
            'handled_at' => $handled ? now() : null,
            'handled_by' => $handled ? $request->user()->id : null,
        ])->save();

        return $this->done($request, back(), $handled ? 'Đã đánh dấu liên hệ là đã xử lý.' : 'Đã chuyển liên hệ về chưa xử lý.');
    }

    /**
     * Trả thông tin liên lạc đầy đủ (danh sách chỉ hiện bản đã che). Mỗi lần xem được ghi nhật ký
     * (ai xem, liên hệ số mấy — không ghi chính thông tin liên lạc) để kiểm tra khi cần.
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  ContactMessage  $contact  liên hệ
     */
    public function reveal(Request $request, ContactMessage $contact): JsonResponse
    {
        Gate::authorize('viewAny', ContactMessage::class);

        ActivityLog::record(null, $request->user(), ActivityAction::ContactRevealed, 'Xem thông tin liên lạc của liên hệ #'.$contact->id.'.');
        $isEmail = str_contains($contact->contact, '@');

        return response()->json([
            'contact' => $contact->contact,
            'href' => ($isEmail ? 'mailto:' : 'tel:').$contact->contact,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Xóa liên hệ (thông tin cá nhân không cần giữ sau khi xử lý xong).
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  ContactMessage  $contact  liên hệ
     */
    public function destroy(Request $request, ContactMessage $contact): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return $this->done($request, back(), 'Đã xóa liên hệ.');
    }
}
