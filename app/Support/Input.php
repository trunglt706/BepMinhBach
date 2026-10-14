<?php

namespace App\Support;

/** Chuẩn hóa dữ liệu nhập theo thói quen người dùng Việt Nam trước khi validate. */
class Input
{
    /**
     * "150.000", "150,000 đ", "150 000" → "150000".
     * Chỉ bỏ dấu phân cách khi đúng nhóm 3 chữ số, để "12,5" không bị hiểu thành 125.
     * Giá trị không hợp lệ được giữ nguyên để validation báo lỗi.
     */
    public static function money(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim(preg_replace('/\s*(vnđ|vnd|đ)$/iu', '', trim($value)));
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $trimmed) || preg_match('/^-?\d{1,3}([.,\s]\d{3})+$/u', $trimmed)) {
            return preg_replace('/[.,\s]/u', '', $trimmed);
        }

        return $trimmed;
    }

    /** "1,5" → "1.5" (dấu phẩy thập phân). */
    public static function decimal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return preg_match('/^-?\d+,\d+$/', $trimmed) ? str_replace(',', '.', $trimmed) : $trimmed;
    }
}
