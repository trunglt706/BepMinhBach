<?php

namespace App\Http\Requests;

use App\Models\CookingBatch;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

/**
 * Bộ lọc trang bài viết công khai (từ khóa, thẻ, đợt nấu).
 * Thẻ/đợt chỉ được chấp nhận khi có bài ĐÃ ĐĂNG — không dùng được để dò tên đợt nội bộ qua ?dot=ID.
 * Giá trị không hợp lệ bị bỏ qua (khách thường sửa URL tay), không trả lỗi.
 */
class PublicPostIndexRequest extends FormRequest
{
    /** Số từ khóa tối đa được xét. */
    public const MAX_WORDS = 6;

    /**
     * Trang công khai: ai cũng xem được.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Chuẩn hóa: cắt từ khóa, ép kiểu ID đợt.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => mb_substr(trim((string) $this->query('q', '')), 0, 100),
            'tag' => is_string($this->query('tag')) ? mb_substr($this->query('tag'), 0, 60) : null,
            'dot' => ctype_digit((string) $this->query('dot')) ? (int) $this->query('dot') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:60'],
            'dot' => ['nullable', 'integer'],
        ];
    }

    /**
     * Từ khóa đã cắt gọn ("" nếu không tìm).
     */
    public function keyword(): string
    {
        return (string) $this->validated('q');
    }

    /**
     * Các từ khóa (tối đa MAX_WORDS), mỗi từ phải xuất hiện trong bài.
     *
     * @return Collection<int, string>
     */
    public function words(): Collection
    {
        return collect(preg_split('/\s+/u', $this->keyword(), -1, PREG_SPLIT_NO_EMPTY))->take(self::MAX_WORDS);
    }

    /**
     * Thẻ đang lọc, chỉ khi thẻ có bài đã đăng.
     */
    public function tag(): ?Tag
    {
        $slug = $this->validated('tag');

        return $slug ? Tag::where('slug', $slug)->whereHas('posts', fn (Builder $q) => $q->published())->first() : null;
    }

    /**
     * Đợt nấu đang lọc, chỉ khi đợt có bài đã đăng (chỉ lấy cột công khai).
     */
    public function batch(): ?CookingBatch
    {
        $id = $this->validated('dot');

        return $id ? CookingBatch::whereKey($id)->whereHas('posts', fn (Builder $q) => $q->published())->first(['id', 'name', 'starts_on']) : null;
    }
}
