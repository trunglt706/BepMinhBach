<?php

namespace App\Models;

use App\Support\PostImageProcessor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Ảnh bài viết (ảnh đại diện hoặc ảnh album), lưu thành nhiều bản WebP theo chiều rộng:
 * file thật là "{PUBLIC_DIR}/{path}-{width}.{EXTENSION}".
 *
 * @property list<int> $widths
 */
class PostImage extends Model
{
    use HasFactory;

    /** Loại ảnh: ảnh đại diện (tối đa một mỗi bài). */
    public const COVER = 'cover';

    /** Loại ảnh: ảnh album. */
    public const GALLERY = 'gallery';

    /** Thư mục dưới public/ chứa ảnh (trùng root của disk "uploads"). */
    public const PUBLIC_DIR = 'uploads';

    /** Định dạng lưu trữ duy nhất. */
    public const EXTENSION = 'webp';

    protected $fillable = ['kind', 'path', 'widths', 'width', 'height', 'caption', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'widths' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Xóa bản ghi → xóa file WebP, nhưng chỉ SAU KHI giao dịch thành công (rollback thì file vẫn còn).
     */
    protected static function booted(): void
    {
        static::deleted(fn (PostImage $image) => DB::afterCommit(
            fn () => app(PostImageProcessor::class)->delete($image->path, $image->widths)
        ));
    }

    /**
     * Tên file (tương đối với disk "uploads") của một bản theo chiều rộng.
     *
     * @param  string  $path  đường dẫn gốc không đuôi
     * @param  int  $width  chiều rộng của bản
     */
    public static function fileName(string $path, int $width): string
    {
        return $path.'-'.$width.'.'.self::EXTENSION;
    }

    /**
     * Bài viết chứa ảnh.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * URL bản nhỏ nhất có chiều rộng ≥ $width (không có thì bản lớn nhất). Bỏ trống: bản lớn nhất.
     *
     * @param  int|null  $width  chiều rộng hiển thị cần
     */
    public function url(?int $width = null): string
    {
        $widths = $this->widths;
        sort($widths);
        $pick = $width ? (collect($widths)->first(fn ($w) => $w >= $width) ?? end($widths)) : end($widths);

        return asset(self::PUBLIC_DIR.'/'.self::fileName($this->path, $pick));
    }

    /**
     * Giá trị srcset để trình duyệt tự chọn bản vừa màn hình: điện thoại tải 480/960, máy tính tải 1600.
     */
    public function srcset(): string
    {
        return collect($this->widths)->sort()
            ->map(fn ($w) => asset(self::PUBLIC_DIR.'/'.self::fileName($this->path, $w)).' '.$w.'w')
            ->implode(', ');
    }

    /**
     * Chiều cao tương ứng với một chiều rộng hiển thị (để khai báo width/height, tránh trang bị giật khi ảnh tải).
     *
     * @param  int  $width  chiều rộng hiển thị
     */
    public function heightFor(int $width): int
    {
        return (int) round($this->height * $width / max(1, $this->width));
    }

    /**
     * Chiều rộng của bản lớn nhất đã lưu.
     */
    public function largestWidth(): int
    {
        return (int) max($this->widths);
    }
}
