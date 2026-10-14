<?php

namespace App\Http\Controllers;

use App\Enums\ContactTopic;
use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang liên hệ công khai: khách gửi lời nhắn cho đội bếp (1 liên hệ / client / ngày). */
class PublicContactController extends Controller
{
    /**
     * Form liên hệ. Chủ đề có thể chọn sẵn qua ?chu-de=volunteer.
     *
     * @param  Request  $request  yêu cầu hiện tại
     */
    public function create(Request $request): View
    {
        return view('public.contact', [
            'topics' => ContactTopic::cases(),
            'selected' => ContactTopic::tryFrom((string) $request->query('chu-de')) ?? ContactTopic::Volunteer,
            'sentToday' => ContactMessage::sentToday(ContactMessage::clientHash($request)),
        ]);
    }

    /**
     * Lưu liên hệ. Không ghi nội dung/thông tin liên lạc vào log.
     *
     * @param  ContactRequest  $request  dữ liệu đã kiểm tra
     */
    public function store(ContactRequest $request): RedirectResponse
    {
        ContactMessage::create([
            'topic' => $request->enum('topic', ContactTopic::class),
            'name' => $request->input('name'),
            'contact' => $request->input('contact'),
            'message' => $request->input('message'),
            'client_hash' => ContactMessage::clientHash($request),
            'sent_on' => today(),
        ]);

        return redirect()->route('contact.create')
            ->with('success', 'Cảm ơn bạn! Đội bếp đã nhận được lời nhắn và sẽ liên hệ lại sớm.');
    }
}
