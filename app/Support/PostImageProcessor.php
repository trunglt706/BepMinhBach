<?php

namespace App\Support;

use App\Models\PostImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Chuyển ảnh tải lên thành các bản WebP nhiều kích thước.
 *
 * Bảo mật: không tin đuôi file hay Content-Type của trình duyệt. Kiểu ảnh được xác định từ nội dung file
 * (getimagesize), ảnh được GIẢI MÃ hoàn toàn rồi MÃ HÓA LẠI, nên file lưu trữ không chứa EXIF/GPS hay dữ liệu
 * lạ nhúng trong file gốc (polyglot). Tên file ngẫu nhiên, file gốc không bao giờ được lưu.
 */
class PostImageProcessor
{
    /** Disk lưu ảnh (config/filesystems.php). */
    public const DISK = 'uploads';

    /** Thư mục con theo năm/tháng: posts/2026/10/… */
    public const DIRECTORY = 'posts';

    /** Độ dài tên file ngẫu nhiên. */
    private const NAME_LENGTH = 32;

    /** Kiểu ảnh GD đọc được (khớp với bmb.post_images.mimes). */
    private const DECODERS = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    /**
     * Giải mã, xoay đúng hướng, tạo các bản WebP theo bmb.post_images.widths (không phóng to ảnh nhỏ).
     *
     * @param  UploadedFile  $file  file đã qua validation của FormRequest
     * @param  string  $directory  thư mục con: posts (ảnh bài viết) hoặc batches (ảnh nền đợt nấu)
     * @return array{path: string, widths: list<int>, width: int, height: int} dữ liệu cho PostImage
     *
     * @throws InvalidImage ảnh hỏng, sai định dạng thật hoặc quá nhiều điểm ảnh (thông báo cho người dùng)
     * @throws RuntimeException lỗi khi thu nhỏ/mã hóa (các file đã ghi được xóa trước khi ném lỗi)
     */
    public function store(UploadedFile $file, string $directory = self::DIRECTORY): array
    {
        $source = $this->decode($file->getRealPath());
        $width = imagesx($source);
        $height = imagesy($source);

        $path = $directory.'/'.now()->format('Y/m').'/'.Str::random(self::NAME_LENGTH);
        $disk = Storage::disk(self::DISK);
        $quality = (int) config('bmb.post_images.quality', 80);

        $written = [];
        try {
            $targets = $this->targetWidths($width);
            foreach ($targets as $target) {
                $disk->put($written[] = PostImage::fileName($path, $target), $this->encode($source, $target, $quality));
            }
        } catch (Throwable $e) {
            $disk->delete($written);
            throw $e;
        } finally {
            imagedestroy($source);
        }

        return ['path' => $path, 'widths' => $targets, 'width' => $width, 'height' => $height];
    }

    /**
     * Xóa mọi bản WebP của một ảnh.
     *
     * @param  string  $path  đường dẫn gốc không đuôi
     * @param  list<int>  $widths  các chiều rộng đã lưu
     */
    public function delete(string $path, array $widths): void
    {
        Storage::disk(self::DISK)->delete(array_map(fn ($w) => PostImage::fileName($path, (int) $w), $widths));
    }

    /**
     * Các chiều rộng cần tạo: mọi mốc nhỏ hơn ảnh gốc + một bản lớn nhất không vượt ảnh gốc.
     *
     * @param  int  $width  chiều rộng ảnh gốc
     * @return list<int> tăng dần, không trùng
     */
    private function targetWidths(int $width): array
    {
        $configured = array_map('intval', config('bmb.post_images.widths'));
        $targets = array_filter($configured, fn ($w) => $w < $width);
        $targets[] = min($width, max($configured));
        $targets = array_values(array_unique($targets));
        sort($targets);

        return $targets;
    }

    /**
     * Thu nhỏ (nếu cần) rồi mã hóa WebP.
     *
     * @param  GdImage  $source  ảnh gốc đã giải mã
     * @param  int  $target  chiều rộng đích
     * @param  int  $quality  chất lượng WebP 0–100
     * @return string dữ liệu file WebP
     *
     * @throws RuntimeException không thu nhỏ/mã hóa được
     */
    private function encode(GdImage $source, int $target, int $quality): string
    {
        $width = imagesx($source);
        $resized = $target === $width
            ? $source
            : imagescale($source, $target, (int) round(imagesy($source) * $target / $width), IMG_BICUBIC);
        if (! $resized instanceof GdImage) {
            throw new RuntimeException('Không thu nhỏ được ảnh.');
        }
        imagesavealpha($resized, true); // giữ nền trong suốt của PNG

        ob_start();
        $ok = imagewebp($resized, null, $quality);
        $bytes = ob_get_clean();
        if ($resized !== $source) {
            imagedestroy($resized);
        }
        if (! $ok || $bytes === '' || $bytes === false) {
            throw new RuntimeException('Không chuyển được ảnh sang WebP.');
        }

        return $bytes;
    }

    /**
     * Đọc ảnh từ nội dung file (không theo đuôi): kiểm tra kiểu thật, giới hạn số điểm ảnh, giải mã bằng GD.
     *
     * @param  string  $file  đường dẫn file tạm
     *
     * @throws InvalidImage file không phải ảnh hợp lệ hoặc quá lớn
     */
    private function decode(string $file): GdImage
    {
        $info = @getimagesize($file);
        if (! $info || ! isset(self::DECODERS[$info[2]]) || ! in_array($info['mime'], config('bmb.post_images.mimes'), true)) {
            throw new InvalidImage('File không phải ảnh JPG, PNG hoặc WebP hợp lệ.');
        }
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w * $h > (int) config('bmb.post_images.max_pixels')) {
            throw new InvalidImage('Ảnh quá lớn (số điểm ảnh). Hãy chọn ảnh nhỏ hơn.');
        }

        $image = @(self::DECODERS[$info[2]])($file);
        if (! $image instanceof GdImage) {
            throw new InvalidImage('Không đọc được ảnh (file hỏng hoặc không đúng định dạng).');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $info[2] === IMAGETYPE_JPEG ? $this->applyExifOrientation($image, $file) : $image;
    }

    /**
     * Ảnh chụp điện thoại thường lưu hướng xoay trong EXIF; xoay thật vào điểm ảnh vì bản WebP không giữ EXIF.
     *
     * @param  GdImage  $image  ảnh JPEG đã giải mã
     * @param  string  $file  đường dẫn file gốc (để đọc EXIF)
     * @return GdImage ảnh đã xoay đúng hướng
     */
    private function applyExifOrientation(GdImage $image, string $file): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (int) (@exif_read_data($file)['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if ($rotated !== $image && $rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
