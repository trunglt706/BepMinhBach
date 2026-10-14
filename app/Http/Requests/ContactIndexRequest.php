<?php

namespace App\Http\Requests;

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Bộ lọc danh sách liên hệ nội bộ: từ khóa (tên, email/SĐT), trạng thái xử lý, chủ đề. Giá trị lạ bị bỏ qua. */
class ContactIndexRequest extends FormRequest
{
    public const STATE_NEW = 'new';

    public const STATE_HANDLED = 'handled';

    public const STATE_ALL = 'all';

    /**
     * Điều phối viên trở lên.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', ContactMessage::class);
    }

    /**
     * Bỏ giá trị không hợp lệ thay vì báo lỗi (người dùng có thể sửa URL tay).
     */
    protected function prepareForValidation(): void
    {
        $state = $this->query('status');
        $topic = $this->query('topic');

        $this->merge([
            'q' => mb_substr(trim((string) $this->query('q', '')), 0, 100),
            'status' => in_array($state, [self::STATE_NEW, self::STATE_HANDLED, self::STATE_ALL], true) ? $state : self::STATE_NEW,
            'topic' => is_string($topic) && ContactTopic::tryFrom($topic) ? $topic : null,
        ]);
    }

    /**
     * Quy tắc kiểm tra.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in([self::STATE_NEW, self::STATE_HANDLED, self::STATE_ALL])],
            'topic' => ['nullable', Rule::enum(ContactTopic::class)],
        ];
    }

    /**
     * Từ khóa tìm theo tên hoặc email/số điện thoại ('' nếu không tìm).
     */
    public function keyword(): string
    {
        return (string) $this->input('q', '');
    }

    /**
     * Trạng thái đang lọc.
     */
    public function state(): string
    {
        return (string) $this->input('status');
    }

    /**
     * Chủ đề đang lọc (nếu có).
     */
    public function topic(): ?ContactTopic
    {
        return ContactTopic::tryFrom((string) $this->input('topic'));
    }
}
