<?php

namespace App\Http\Requests;

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Form liên hệ công khai.
 * - Email hoặc số điện thoại Việt Nam (để đội Góp Gió liên lạc lại), chuẩn hóa trước khi kiểm tra.
 * - Ô "website" là bẫy bot (ẩn với người dùng): có giá trị thì từ chối.
 * - Mỗi client (IP đã băm) hoặc mỗi email/SĐT chỉ gửi 1 liên hệ / ngày.
 */
class ContactRequest extends FormRequest
{
    /** Số điện thoại Việt Nam sau khi bỏ khoảng trắng/dấu chấm/gạch: 0xxxxxxxxx hoặc +84xxxxxxxxx. */
    public const PHONE_PATTERN = '/^(?:\+84|0)\d{9,10}$/';

    /**
     * Trang công khai: ai cũng gửi được (đã có giới hạn tần suất và 1 lần/ngày).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Chuẩn hóa: cắt khoảng trắng, email viết thường, số điện thoại bỏ ký tự phân cách.
     */
    protected function prepareForValidation(): void
    {
        $contact = trim((string) $this->input('contact', ''));
        $contact = str_contains($contact, '@')
            ? mb_strtolower($contact)
            : (string) preg_replace('/[\s.\-()]/', '', $contact);

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'contact' => $contact,
            'message' => trim((string) $this->input('message', '')),
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
            'topic' => ['required', Rule::enum(ContactTopic::class)],
            'name' => ['required', 'string', 'max:100'],
            'contact' => ['required', 'string', 'max:150', function (string $attribute, mixed $value, Closure $fail) {
                $value = (string) $value;
                $valid = str_contains($value, '@')
                    ? filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                    : (bool) preg_match(self::PHONE_PATTERN, $value);
                if (! $valid) {
                    $fail('Vui lòng nhập email hoặc số điện thoại hợp lệ (ví dụ 0901 234 567).');
                }
            }],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * Sau các quy tắc cơ bản: chặn gửi lần thứ hai trong ngày (theo client hoặc theo email/SĐT).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (ContactMessage::sentToday(ContactMessage::clientHash($this), (string) $this->input('contact'))) {
                $validator->errors()->add('limit', 'Bạn đã gửi một liên hệ hôm nay. Đội Góp Gió sẽ phản hồi sớm; bạn có thể gửi thêm vào ngày mai.');
            }
        }];
    }

    /**
     * Tên trường tiếng Việt trong thông báo lỗi.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'topic' => 'chủ đề',
            'name' => 'họ tên',
            'contact' => 'email hoặc số điện thoại',
            'message' => 'nội dung',
            'website' => 'trường này',
        ];
    }
}
