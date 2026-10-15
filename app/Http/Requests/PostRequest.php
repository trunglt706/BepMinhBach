<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Support\PostHtml;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tạo/sửa bài viết: nội dung HTML (lọc khi lưu), thẻ, chương trình, ảnh đại diện và album.
 * Ảnh: kiểm tra kiểu theo nội dung file + kích thước tại đây; PostImageProcessor còn giải mã và mã hóa lại.
 */
class PostRequest extends FormRequest
{
    /** Số thẻ tối đa mỗi bài. */
    public const MAX_TAGS = 10;

    /**
     * Sửa: PostPolicy::update; tạo: PostPolicy::create.
     */
    public function authorize(): bool
    {
        $post = $this->route('post');
        $post ? Gate::authorize('update', $post) : Gate::authorize('create', Post::class);

        return true;
    }

    /**
     * Chuẩn hóa: cắt tiêu đề/tóm tắt; "Bữa trưa, cơm chay; Hà Nội" → tag_list ['Bữa trưa', 'cơm chay', 'Hà Nội']
     * (dấu phẩy hoặc chấm phẩy, bỏ trùng không phân biệt hoa thường).
     */
    protected function prepareForValidation(): void
    {
        $raw = is_string($this->input('tags')) ? $this->input('tags') : '';
        $tags = collect(preg_split('/[,;]/u', $raw))
            ->map(fn ($t) => trim(preg_replace('/\s+/u', ' ', $t)))
            ->filter(fn ($t) => $t !== '')
            ->unique(fn ($t) => mb_strtolower($t))
            ->values()
            ->all();

        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'excerpt' => is_string($this->input('excerpt')) && trim($this->input('excerpt')) !== '' ? trim($this->input('excerpt')) : null,
            'cooking_batch_id' => $this->input('cooking_batch_id') ?: null,
            'tag_list' => $tags,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Kiểu ảnh kiểm tra theo NỘI DUNG file (mimetypes/mimes dùng finfo), không theo đuôi tên file.
        // Máy chủ còn giải mã + mã hóa lại thành WebP (PostImageProcessor), nên file lạ không lọt qua.
        $image = ['file', 'max:'.config('bmb.post_images.max_upload_kb'),
            'mimetypes:'.implode(',', config('bmb.post_images.mimes')), 'mimes:jpg,jpeg,png,webp'];

        return [
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            // Chỉ gắn được với chương trình trong phạm vi được xem (giữ nguyên chương trình đang gắn thì luôn được).
            'cooking_batch_id' => ['nullable', 'integer', Rule::exists('cooking_batches', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $current = $this->route('post')?->cooking_batch_id;
                    if ($value && (int) $value !== $current && ! CookingBatch::visibleTo($this->user())->whereKey($value)->exists()) {
                        $fail('Bạn chỉ gắn được bài với chương trình mình phụ trách.');
                    }
                }],
            'tag_list' => ['array', 'max:'.self::MAX_TAGS],
            'tag_list.*' => ['string', 'max:40', 'regex:/[\pL\pN]/u'],
            // Quản trị viên: lưu nháp hoặc đăng ngay. Người khác: lưu nháp hoặc gửi duyệt — không tự đăng được.
            'save_as' => [$this->route('post') ? 'prohibited' : 'required', Rule::in($this->user()->isAdmin()
                ? [PostStatus::Draft->value, PostStatus::Published->value]
                : [PostStatus::Draft->value, PostStatus::Pending->value])],
            'cover' => ['nullable', ...$image],
            'remove_cover' => ['nullable', 'boolean'],
            'gallery' => ['nullable', 'array', 'max:'.config('bmb.post_images.max_album')],
            'gallery.*' => $image,
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Kiểm tra sau: nội dung không rỗng sau khi lọc HTML; tổng số ảnh album (giữ lại + thêm mới) không vượt giới hạn.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('body') && PostHtml::toText(PostHtml::sanitize($this->input('body'))) === '') {
                $validator->errors()->add('body', 'Vui lòng nhập nội dung bài viết.');
            }

            $max = (int) config('bmb.post_images.max_album');
            $post = $this->route('post');
            $removing = collect($this->input('remove_images', []))->map(fn ($id) => (int) $id);
            $kept = $post ? $post->gallery()->whereNotIn('id', $removing)->count() : 0;
            $adding = count($this->file('gallery', []));
            if ($kept + $adding > $max) {
                $validator->errors()->add('gallery', "Mỗi bài tối đa {$max} ảnh album (đang có {$kept}, thêm {$adding}).");
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'tiêu đề',
            'excerpt' => 'tóm tắt',
            'body' => 'nội dung',
            'cooking_batch_id' => 'chương trình',
            'tag_list' => 'thẻ',
            'tag_list.*' => 'thẻ',
            'cover' => 'ảnh đại diện',
            'gallery' => 'ảnh album',
            'gallery.*' => 'ảnh album',
            'captions.*' => 'chú thích ảnh',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $kb = (int) config('bmb.post_images.max_upload_kb');
        $size = 'Mỗi ảnh tối đa '.round($kb / 1024).' MB.';
        $type = 'Chỉ nhận ảnh JPG, PNG hoặc WebP.';

        return [
            'save_as.in' => 'Bạn chỉ lưu nháp hoặc gửi duyệt được; quản trị viên sẽ duyệt để đăng bài.',
            'tag_list.max' => 'Tối đa '.self::MAX_TAGS.' thẻ cho mỗi bài viết.',
            'tag_list.*.max' => 'Mỗi thẻ tối đa 40 ký tự.',
            'tag_list.*.regex' => 'Thẻ phải có chữ hoặc số.',
            'cover.max' => $size, 'gallery.*.max' => $size,
            'cover.mimetypes' => $type, 'cover.mimes' => $type, 'cover.file' => $type,
            'gallery.*.mimetypes' => $type, 'gallery.*.mimes' => $type, 'gallery.*.file' => $type,
            'gallery.*.uploaded' => 'Tải ảnh lên không thành công (có thể ảnh quá lớn).',
            'cover.uploaded' => 'Tải ảnh lên không thành công (có thể ảnh quá lớn).',
            'gallery.max' => 'Mỗi bài tối đa '.config('bmb.post_images.max_album').' ảnh album.',
        ];
    }

    /**
     * Lỗi của từng thẻ/từng ảnh hiển thị ở ô nhập tương ứng ("tags", "gallery") rồi trả lỗi như thường.
     *
     * @param  Validator  $validator  bộ kiểm tra đã thất bại
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        foreach ($errors->keys() as $key) {
            $target = match (true) {
                str_starts_with($key, 'tag_list') => 'tags',
                str_starts_with($key, 'gallery.') => 'gallery',
                default => null,
            };
            if ($target) {
                if (! in_array($errors->first($key), $errors->get($target), true)) {
                    $errors->add($target, $errors->first($key));
                }
                $errors->forget($key);
            }
        }

        parent::failedValidation($validator);
    }
}
