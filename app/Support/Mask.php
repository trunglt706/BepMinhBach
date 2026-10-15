<?php

namespace App\Support;

/**
 * Che thông tin liên lạc khi hiển thị (email, số điện thoại): đủ để nhận ra, không đủ để dùng.
 * Bản đầy đủ chỉ lấy qua thao tác "Hiện" có kiểm tra quyền và ghi nhật ký (ContactMessageController::reveal).
 */
class Mask
{
    /**
     * Email: "lan.nguyen@gmail.com" → "l*****@gmail.com"; số điện thoại: "0901234567" → "090****567".
     *
     * @param  string|null  $value  email hoặc số điện thoại
     */
    public static function contact(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 1).'*****@'.$domain;
        }

        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) < 7) {
            return str_repeat('*', max(strlen($digits), 5));
        }

        return substr($digits, 0, 3).str_repeat('*', strlen($digits) - 6).substr($digits, -3);
    }
}
