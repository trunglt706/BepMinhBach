<?php

namespace App\Support;

/** Chuẩn hóa dữ liệu nhập theo thói quen người dùng Việt Nam trước khi validate. */
class Input
{
    /**
     * "150.000", "150,000 đ", "150 000" → "150000".
     * Chỉ bỏ dấu phân cách khi đúng nhóm 3 chữ số, để "12,5" không bị hiểu thành 125.
     * Giá trị không hợp lệ được giữ nguyên để validation báo lỗi.
     *
     * @param  mixed  $value  giá trị người dùng nhập
     * @return mixed chuỗi chữ số, null nếu trống, hoặc giá trị gốc nếu không nhận dạng được
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

    /**
     * Số thập phân kiểu Việt Nam: "1,5" → "1.5". Giá trị khác giữ nguyên để validation báo lỗi.
     *
     * @param  mixed  $value  giá trị người dùng nhập
     * @return mixed chuỗi số dùng dấu chấm, null nếu trống, hoặc giá trị gốc
     */
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

    /**
     * Số lượng kiểu Việt Nam (ô số lượng tự định dạng): "1.500,25" → "1500.25", "1.500" → "1500", "2,5" → "2.5".
     * Có dấu phẩy: dấu chấm là phân cách hàng nghìn. Không có dấu phẩy: dấu chấm chỉ bị coi là phân cách
     * hàng nghìn khi đúng nhóm 3 chữ số ("1.500"), còn lại là dấu thập phân ("2.5").
     * Giá trị khác giữ nguyên để validation báo lỗi.
     *
     * @param  mixed  $value  giá trị người dùng nhập
     * @return mixed chuỗi số dùng dấu chấm, null nếu trống, hoặc giá trị gốc
     */
    public static function quantity(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $trimmed)) {
            return str_replace(['.', ','], ['', '.'], $trimmed);
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $trimmed)) {
            return str_replace('.', '', $trimmed);
        }

        return self::decimal($trimmed);
    }
}
