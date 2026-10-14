<?php

/*
 * Cấu hình riêng của BếpMinhBạch.
 */
return [

    /*
     * Giới hạn số yêu cầu mỗi phút để chống spam.
     * - writes: thao tác ghi (thêm/sửa/xóa/đổi trạng thái) của MỖI tài khoản.
     * - reads: mở trang/tải dữ liệu của mỗi tài khoản (rộng rãi, chỉ chặn gọi dồn dập).
     * - public: trang công khai (bài viết) theo địa chỉ IP của khách.
     * Đăng nhập có giới hạn riêng (5 lần sai/email+IP) trong LoginController.
     */
    'rate_limits' => [
        'writes' => (int) env('BMB_RATE_WRITES', 40),
        'reads' => (int) env('BMB_RATE_READS', 300),
        'public' => (int) env('BMB_RATE_PUBLIC', 120),
    ],

    /*
     * Ảnh bài viết. Mọi ảnh được giải mã rồi mã hóa lại thành WebP (bỏ EXIF/GPS, loại bỏ mã độc ẩn trong file),
     * tạo nhiều kích thước để điện thoại tải ảnh nhỏ, máy tính tải ảnh nét (srcset).
     */
    'post_images' => [
        'max_album' => (int) env('BMB_POST_MAX_IMAGES', 12),   // số ảnh album tối đa mỗi bài
        'max_upload_kb' => (int) env('BMB_POST_MAX_UPLOAD_KB', 10240), // 10 MB mỗi ảnh (trước khi nén)
        'max_pixels' => 40_000_000,                             // chặn ảnh "bom giải nén" (≈ 8000×5000)
        'widths' => [480, 960, 1600],                           // các bản WebP: điện thoại / máy tính bảng / máy tính
        'quality' => 80,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],   // không nhận SVG/GIF/HEIC
    ],

    // Lượt xem: mỗi người xem (IP + trình duyệt, đã băm) chỉ được tính 1 lần / bài trong khoảng này.
    'post_view_window_minutes' => (int) env('BMB_POST_VIEW_WINDOW', 360),

];
