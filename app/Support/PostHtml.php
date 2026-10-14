<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Lọc HTML từ trình soạn thảo bài viết theo danh sách cho phép (allowlist).
 * Chỉ giữ định dạng văn bản; bỏ mọi script, style, class, sự kiện on*, iframe, ảnh nhúng
 * (ảnh bài viết đi qua phần tải ảnh riêng, được kiểm tra và chuyển WebP).
 */
class PostHtml
{
    /** Thẻ khối được giữ (không thuộc tính). */
    private const BLOCKS = ['p', 'div', 'h2', 'h3', 'blockquote', 'pre', 'ul', 'ol', 'li', 'br'];

    /** Thẻ định dạng chữ được giữ (không thuộc tính). */
    private const INLINE = ['strong', 'b', 'em', 'i', 'u', 'del', 's', 'code'];

    /** Liên kết: chỉ giữ href (http/https/mailto), luôn gắn rel an toàn. */
    private const LINK = 'a';

    /** Giao thức liên kết được phép (chặn javascript:, data:…). */
    private const LINK_SCHEMES = ['http', 'https', 'mailto'];

    /** rel gắn vào mọi liên kết do người dùng nhập. */
    public const LINK_REL = 'nofollow noopener noreferrer ugc';

    /**
     * Lọc HTML: hạ <h1> thành <h2>, chỉ giữ thẻ trong danh sách cho phép, bỏ khối rỗng ở cuối.
     *
     * @param  string|null  $html  HTML từ trình soạn thảo (hoặc chữ thuần)
     * @return string HTML an toàn để in trực tiếp ("" nếu trống)
     */
    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Trình soạn thảo dùng <h1> cho tiêu đề phụ; trang đã có <h1> là tiêu đề bài → hạ xuống <h2> (tốt cho SEO).
        $html = preg_replace(['#<h1(\s[^>]*)?>#i', '#</h1\s*>#i'], ['<h2>', '</h2>'], $html);

        $clean = self::sanitizer()->sanitize($html);

        // Bỏ các khối rỗng do trình soạn thảo để lại ở cuối.
        return trim(preg_replace('#(<(div|p)>(\s|<br\s*/?>|&nbsp;)*</\2>\s*)+$#i', '', $clean));
    }

    /**
     * Chữ thuần (cho tìm kiếm, tóm tắt, mô tả SEO): bỏ thẻ, giải mã thực thể, gộp khoảng trắng.
     * Thẻ khối (mở và đóng) được tách bằng khoảng trắng để chữ của hai đoạn không dính nhau.
     *
     * @param  string  $html  HTML đã lọc
     * @return string chữ thuần trên một dòng
     */
    public static function toText(string $html): string
    {
        $text = preg_replace('#</?(br|p|div|h[1-6]|li|ul|ol|blockquote|pre)\b[^>]*>#i', ' $0 ', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * Bộ lọc Symfony dùng chung (tạo một lần mỗi tiến trình).
     */
    private static function sanitizer(): HtmlSanitizer
    {
        static $sanitizer;
        if ($sanitizer) {
            return $sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(self::LINK_SCHEMES)
            ->allowRelativeLinks(false)
            ->allowElement(self::LINK, ['href'])
            // Liên kết do người dùng nhập: không truyền uy tín SEO, không cho trang đích điều khiển tab này.
            ->forceAttribute(self::LINK, 'rel', self::LINK_REL)
            ->withMaxInputLength(200_000);

        foreach ([...self::BLOCKS, ...self::INLINE] as $element) {
            $config = $config->allowElement($element, []);
        }

        return $sanitizer = new HtmlSanitizer($config);
    }
}
