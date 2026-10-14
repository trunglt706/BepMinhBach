<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;

/** Bộ lọc danh sách bài viết nội bộ. Giá trị lạ bị bỏ qua thay vì báo lỗi. */
class PostIndexRequest extends FormRequest
{
    /**
     * Quyền xem danh sách được kiểm tra trong controller (PostPolicy::viewAny).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Chuẩn hóa từ khóa và trạng thái.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => mb_substr(trim((string) $this->query('q', '')), 0, 100),
            'status' => PostStatus::tryFrom((string) $this->query('status'))?->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
        ];
    }

    /**
     * Từ khóa tìm theo tiêu đề ("" nếu không lọc).
     */
    public function keyword(): string
    {
        return (string) $this->validated('q');
    }

    /**
     * Trạng thái cần lọc (null = tất cả).
     */
    public function status(): ?PostStatus
    {
        return PostStatus::tryFrom((string) $this->validated('status'));
    }
}
