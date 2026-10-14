<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Định dạng hiển thị kiểu Việt Nam. */
class Format
{
    private const WEEKDAYS = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];

    public static function vnd(?int $amount): string
    {
        return $amount === null ? 'Chưa nhập' : number_format($amount, 0, ',', '.').' đ';
    }

    public static function number(?int $value, string $empty = 'Chưa nhập'): string
    {
        return $value === null ? $empty : number_format($value, 0, ',', '.');
    }

    /** Bỏ số 0 thừa ở phần thập phân: "2.500" → "2,5". */
    public static function quantity(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $formatted = number_format((float) $value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /** Giá trị cho input số trong form (dấu chấm thập phân, không thừa số 0). */
    public static function quantityInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    public static function dateTime(?\DateTimeInterface $value): string
    {
        return $value ? $value->format('H:i d/m/Y') : '—';
    }

    public static function date(?\DateTimeInterface $value): string
    {
        return $value ? $value->format('d/m/Y') : '—';
    }

    /** "Thứ ba, 14/10/2026" */
    public static function longDate(\DateTimeInterface $value): string
    {
        return self::WEEKDAYS[(int) $value->format('w')].', '.$value->format('d/m/Y');
    }

    /** Thời gian tương đối thân thiện cho hạn công việc: "Hôm nay 09:00", "Ngày mai 07:00", "07:00 16/10/2026". */
    public static function relative(?\DateTimeInterface $value): string
    {
        if (! $value) {
            return '—';
        }

        $date = CarbonImmutable::instance($value);
        $time = $date->format('H:i');

        return match (true) {
            $date->isToday() => 'Hôm nay '.$time,
            $date->isTomorrow() => 'Ngày mai '.$time,
            $date->isYesterday() => 'Hôm qua '.$time,
            default => $time.' '.$date->format('d/m/Y'),
        };
    }
}
