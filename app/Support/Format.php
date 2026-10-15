<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Định dạng hiển thị kiểu Việt Nam. */
class Format
{
    /** Chữ hiển thị khi số liệu chưa có (khác với 0). */
    public const NOT_ENTERED = '—';

    /** Chữ hiển thị cho ngày/số lượng trống. */
    public const EMPTY = '—';

    private const WEEKDAYS = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];

    /**
     * Số tiền VND: 1500000 → "1.500.000 đ"; null → "—".
     *
     * @param  int|null  $amount  số tiền (đồng)
     */
    public static function vnd(?int $amount): string
    {
        return $amount === null ? self::NOT_ENTERED : number_format($amount, 0, ',', '.').' đ';
    }

    /**
     * Số nguyên có dấu chấm hàng nghìn: 1500 → "1.500".
     *
     * @param  int|null  $value  giá trị
     * @param  string  $empty  chữ hiển thị khi null
     */
    public static function number(?int $value, string $empty = self::NOT_ENTERED): string
    {
        return $value === null ? $empty : number_format($value, 0, ',', '.');
    }

    /**
     * Số lượng nguyên liệu, bỏ số 0 thừa ở phần thập phân: "2.500" → "2,5".
     *
     * @param  mixed  $value  số (chuỗi decimal từ DB hoặc số)
     */
    public static function quantity(mixed $value): string
    {
        if ($value === null || $value === '') {
            return self::EMPTY;
        }

        $formatted = number_format((float) $value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * Giá trị cho ô nhập số lượng trong form (dấu chấm thập phân, không thừa số 0).
     *
     * @param  mixed  $value  số (chuỗi decimal từ DB hoặc số)
     */
    public static function quantityInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    /**
     * Giá trị cho ô số lượng tự định dạng trong form: "1500.250" → "1.500,25"; null → "".
     *
     * @param  mixed  $value  số (chuỗi decimal từ DB hoặc số)
     */
    public static function quantityField(mixed $value): string
    {
        return $value === null || $value === '' ? '' : self::quantity($value);
    }

    /**
     * Ngày giờ: "09:30 14/10/2026".
     *
     * @param  DateTimeInterface|null  $value  thời điểm
     */
    public static function dateTime(?DateTimeInterface $value): string
    {
        return $value ? $value->format('H:i d/m/Y') : self::EMPTY;
    }

    /**
     * Ngày: "14/10/2026".
     *
     * @param  DateTimeInterface|null  $value  thời điểm
     */
    public static function date(?DateTimeInterface $value): string
    {
        return $value ? $value->format('d/m/Y') : self::EMPTY;
    }

    /**
     * Ngày kèm thứ: "Thứ ba, 14/10/2026".
     *
     * @param  DateTimeInterface  $value  thời điểm
     */
    public static function longDate(DateTimeInterface $value): string
    {
        return self::WEEKDAYS[(int) $value->format('w')].', '.$value->format('d/m/Y');
    }

    /**
     * Thời gian tương đối cho hạn công việc: "Hôm nay 09:00", "Ngày mai 07:00", "07:00 16/10/2026".
     *
     * @param  DateTimeInterface|null  $value  thời điểm
     */
    public static function relative(?DateTimeInterface $value): string
    {
        if (! $value) {
            return self::EMPTY;
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
