<?php

namespace App\Models;

use App\Enums\SponsorType;
use App\Support\PostHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Đơn vị tài trợ / hỗ trợ chương trình. Thông tin công khai (tên, logo, mô tả, website) — không lưu người liên hệ,
 * số điện thoại hay số tiền tài trợ. Ẩn (is_active = false) thì không hiện ở trang công khai.
 *
 * @property SponsorType $type
 */
class Sponsor extends Model
{
    use HasFactory;

    /** Đường dẫn dự phòng khi tên không tạo được slug. */
    public const FALLBACK_SLUG = 'don-vi';

    protected $fillable = ['name', 'type', 'description', 'website', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SponsorType::class,
            'is_active' => 'boolean',
            'logo_widths' => 'array',
        ];
    }

    /**
     * Giới thiệu soạn bằng trình soạn thảo: luôn lọc HTML khi gán (như nội dung bài viết) nên trang công khai
     * in thẳng được. Chữ thuần (không có thẻ đóng — ví dụ trình duyệt tắt JS) được chuyển mỗi dòng thành một đoạn.
     * Trống sau khi lọc → null.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function description(): Attribute
    {
        return Attribute::make(set: function (?string $value) {
            // Trình soạn thảo luôn sinh thẻ đóng/<br>; không có thì là chữ thuần (kể cả khi có ký tự "<").
            if ($value !== null && ! preg_match('#</[a-z][a-z0-9]*\s*>|<br\s*/?>#i', $value)) {
                $value = self::plainToHtml($value);
            }
            $html = PostHtml::sanitize($value);

            return PostHtml::toText($html) === '' ? null : $html;
        });
    }

    /**
     * Chữ thuần → HTML: escape, mỗi dòng không rỗng thành một <div> (giống định dạng của trình soạn thảo).
     *
     * @param  string  $text  chữ thuần
     */
    public static function plainToHtml(string $text): string
    {
        return collect(preg_split('/\R/u', trim($text)))
            ->map(fn ($line) => trim($line))
            ->map(fn ($line) => '<div>'.($line === '' ? '<br>' : e($line)).'</div>')
            ->implode('');
    }

    /**
     * Giới thiệu dạng chữ thuần (mô tả SEO, tóm tắt).
     */
    public function descriptionText(): string
    {
        return $this->description ? PostHtml::toText($this->description) : '';
    }

    /**
     * Tạo đường dẫn từ tên khi tạo mới (đổi tên sau này giữ nguyên đường dẫn đã chia sẻ).
     */
    protected static function booted(): void
    {
        static::creating(function (Sponsor $sponsor) {
            $base = Str::slug($sponsor->name) ?: self::FALLBACK_SLUG;
            $slug = $base;
            for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $sponsor->slug = $slug;
        });
    }

    /**
     * Dùng slug trong URL công khai.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Chương trình đơn vị đã tham gia (kèm ghi chú đóng góp).
     *
     * @return BelongsToMany<CookingBatch, $this>
     */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(CookingBatch::class, 'batch_sponsor')->withPivot('contribution')->withTimestamps();
    }

    /**
     * Đơn vị hiện ở trang công khai.
     *
     * @param  Builder<Sponsor>  $query
     * @return Builder<Sponsor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Logo dưới dạng PostImage (không lưu DB) để dùng chung url()/srcset()/x-post-img.
     */
    public function logo(): ?PostImage
    {
        if (! $this->logo_path || ! $this->logo_widths) {
            return null;
        }

        return (new PostImage)->forceFill([
            'kind' => PostImage::COVER,
            'path' => $this->logo_path,
            'widths' => $this->logo_widths,
            'width' => $this->logo_width,
            'height' => $this->logo_height,
        ]);
    }

    /**
     * Chữ viết tắt hiện thay logo khi chưa có ảnh: "Siêu thị Xanh" → "SX".
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }
}
