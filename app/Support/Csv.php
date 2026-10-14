<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Xuất CSV UTF-8 (có BOM để Excel đọc đúng tiếng Việt), chống spreadsheet formula injection. */
class Csv
{
    /**
     * @param  callable(callable(array): void): void  $writeRows  nhận hàm $put để ghi từng dòng
     */
    public static function download(string $filename, callable $writeRows): StreamedResponse
    {
        return response()->streamDownload(function () use ($writeRows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            $writeRows(function (array $row) use ($handle) {
                fputcsv($handle, array_map([self::class, 'sanitize'], $row), ',', '"', '');
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Chuỗi bắt đầu bằng = + - @ hoặc ký tự điều khiển được thêm dấu ' để bảng tính không coi là công thức. */
    public static function sanitize(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }

        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r\n]/', $value) ? "'".$value : $value;
    }
}
