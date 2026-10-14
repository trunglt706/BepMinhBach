<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Support\PostHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Bài viết công khai (tin hoạt động, lời cảm ơn…). Có thể gắn với một đợt nấu hoặc không.
 *
 * @property PostStatus $status
 * @property string $body HTML đã lọc
 * @property string|null $body_text chữ thuần của nội dung
 */
class Post extends Model
{
    use HasFactory;

    /** Đường dẫn dự phòng khi tiêu đề không tạo được slug (ví dụ toàn ký tự đặc biệt). */
    public const FALLBACK_SLUG = 'bai-viet';

    /** Tốc độ đọc ước tính (từ/phút) để hiện "x phút đọc". */
    public const WORDS_PER_MINUTE = 200;

    protected $fillable = [
        'title',
        'excerpt',
        'body',
        'cooking_batch_id',
    ];

    protected $attributes = [
        'status' => PostStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    /**
     * Nội dung luôn được lọc HTML khi gán (từ form, seeder hay tinker đều vậy), kèm bản chữ thuần cho tìm kiếm.
     * Nhờ đó trang công khai có thể in thẳng HTML mà không sợ XSS.
     *
     * @return Attribute<string, string|null>
     */
    protected function body(): Attribute
    {
        return Attribute::make(set: function (?string $value) {
            $html = PostHtml::sanitize($value);

            return ['body' => $html, 'body_text' => PostHtml::toText($html)];
        });
    }

    /**
     * Sự kiện model: dọn ảnh khi xóa bài; tạo đường dẫn (slug) từ tiêu đề.
     */
    protected static function booted(): void
    {
        // Xóa bài: xóa từng ảnh để file WebP cũng được dọn (khóa ngoại cascade không chạy sự kiện model).
        static::deleting(fn (Post $post) => $post->images()->get()->each->delete());

        // Đường dẫn tạo từ tiêu đề; chỉ đổi theo tiêu đề khi bài CHƯA từng đăng (không làm hỏng link đã chia sẻ).
        static::saving(function (Post $post) {
            if (! $post->slug || ($post->isDirty('title') && $post->published_at === null)) {
                $post->slug = static::uniqueSlug($post->title, $post->id);
            }
        });
    }

    /**
     * Đường dẫn duy nhất từ tiêu đề: "Cơm chay CN" → "com-chay-cn", trùng thì thêm "-2", "-3"…
     *
     * @param  string  $title  tiêu đề bài
     * @param  int|null  $ignoreId  bỏ qua chính bài đang sửa khi kiểm tra trùng
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($title), 200, '') ?: self::FALLBACK_SLUG;
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Đợt nấu liên quan (nếu có).
     *
     * @return BelongsTo<CookingBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CookingBatch::class, 'cooking_batch_id');
    }

    /**
     * Người viết (chỉ hiện trong trang quản lý, không hiện công khai).
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Thẻ của bài, sắp theo tên.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /**
     * Mọi ảnh của bài (ảnh đại diện + album).
     *
     * @return HasMany<PostImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class);
    }

    /**
     * Ảnh đại diện (tối đa một).
     *
     * @return HasOne<PostImage, $this>
     */
    public function cover(): HasOne
    {
        return $this->hasOne(PostImage::class)->where('kind', PostImage::COVER);
    }

    /**
     * Ảnh album theo thứ tự thêm.
     *
     * @return HasMany<PostImage, $this>
     */
    public function gallery(): HasMany
    {
        return $this->hasMany(PostImage::class)->where('kind', PostImage::GALLERY)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Bài khách xem được: đã đăng và tới thời điểm đăng.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Khách xem được bài này chưa (cùng điều kiện với scopePublished).
     */
    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published && (bool) $this->published_at?->lte(now());
    }

    /**
     * Tóm tắt cho danh sách/mô tả SEO: phần tóm tắt nhập tay, nếu không có thì cắt từ nội dung (chữ thuần).
     *
     * @param  int  $limit  số ký tự tối đa
     */
    public function summary(int $limit = 180): string
    {
        return Str::limit($this->excerpt ?: (string) $this->body_text, $limit);
    }

    /**
     * Thời gian đọc ước tính (phút, tối thiểu 1).
     */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(Str::ascii((string) $this->body_text)) / self::WORDS_PER_MINUTE));
    }
}
