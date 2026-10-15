<?php

namespace App\Models;

use App\Enums\SponsorType;
use Illuminate\Database\Eloquent\Builder;
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
