<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Gom số liệu thành nhóm theo thời gian cho biểu đồ cột (render phía server, không cần thư viện JS). */
class Charts
{
    /** Nhóm theo ngày. */
    public const MODE_DAY = 'day';

    /** Nhóm theo tuần (bắt đầu thứ Hai). */
    public const MODE_WEEK = 'week';

    /** Nhóm theo tháng. */
    public const MODE_MONTH = 'month';

    /** Khoảng ≤ số ngày này: nhóm theo ngày. */
    private const DAILY_MAX_DAYS = 14;

    /** Khoảng ≤ số ngày này: nhóm theo tuần; dài hơn: theo tháng. */
    private const WEEKLY_MAX_DAYS = 120;

    /**
     * Chia khoảng [$from, $to] thành các nhóm: ≤ 14 ngày → theo ngày, ≤ 120 ngày → theo tuần (bắt đầu thứ Hai),
     * dài hơn → theo tháng. Mọi nhóm đều có mặt (kể cả nhóm không có đợt nào) để trục thời gian liên tục.
     *
     * @param  CarbonInterface  $from  đầu khoảng
     * @param  CarbonInterface  $to  cuối khoảng
     * @return list<array{key: string, label: string, title: string}> label ngắn cho trục, title đầy đủ cho tooltip/bảng
     */
    public static function buckets(CarbonInterface $from, CarbonInterface $to): array
    {
        $mode = self::mode($from, $to);
        $cursor = self::bucketStart(CarbonImmutable::instance($from), $mode);
        $end = CarbonImmutable::instance($to);
        $buckets = [];

        while ($cursor <= $end) {
            $buckets[] = match ($mode) {
                self::MODE_DAY => ['key' => self::key($cursor, $mode), 'label' => $cursor->format('d/m'), 'title' => 'Ngày '.$cursor->format('d/m/Y')],
                self::MODE_WEEK => ['key' => self::key($cursor, $mode), 'label' => $cursor->format('d/m'),
                    'title' => 'Tuần '.$cursor->format('d/m').' – '.$cursor->addDays(6)->format('d/m/Y')],
                self::MODE_MONTH => ['key' => self::key($cursor, $mode), 'label' => 'Th'.$cursor->month, 'title' => 'Tháng '.$cursor->format('m/Y')],
            };
            $cursor = match ($mode) {
                self::MODE_DAY => $cursor->addDay(),
                self::MODE_WEEK => $cursor->addWeek(),
                self::MODE_MONTH => $cursor->addMonthNoOverflow(),
            };
        }

        return $buckets;
    }

    /**
     * Khóa nhóm của một thời điểm, cùng quy tắc với buckets().
     *
     * @param  CarbonInterface  $date  thời điểm cần xếp nhóm
     * @param  CarbonInterface  $from  đầu khoảng (quyết định cách nhóm)
     * @param  CarbonInterface  $to  cuối khoảng
     */
    public static function keyFor(CarbonInterface $date, CarbonInterface $from, CarbonInterface $to): string
    {
        $mode = self::mode($from, $to);

        return self::key(self::bucketStart(CarbonImmutable::instance($date), $mode), $mode);
    }

    /**
     * Cộng giá trị theo nhóm thời gian cho nhiều chuỗi.
     *
     * @param  Collection<int, mixed>  $items  bản ghi cần gom
     * @param  callable(mixed): CarbonInterface  $date  hàm lấy thời điểm của bản ghi
     * @param  array<string, callable(mixed): ?int>  $series  khóa chuỗi => hàm lấy giá trị (null = chưa nhập, không cộng)
     * @param  CarbonInterface  $from  đầu khoảng
     * @param  CarbonInterface  $to  cuối khoảng
     * @return list<array{label: string, title: string, values: array<string, int>}>
     */
    public static function groupByTime(Collection $items, callable $date, array $series, CarbonInterface $from, CarbonInterface $to): array
    {
        $groups = [];
        foreach (self::buckets($from, $to) as $bucket) {
            $groups[$bucket['key']] = ['label' => $bucket['label'], 'title' => $bucket['title'], 'values' => array_fill_keys(array_keys($series), 0)];
        }

        foreach ($items as $item) {
            $key = self::keyFor($date($item), $from, $to);
            if (! isset($groups[$key])) {
                continue;
            }
            foreach ($series as $name => $value) {
                $groups[$key]['values'][$name] += (int) $value($item);
            }
        }

        return array_values($groups);
    }

    /**
     * Làm tròn lên số "đẹp" cho đỉnh trục: 1, 2, 2.5, 5 × 10^n (0 → 10 để trục không suy biến).
     *
     * @param  int  $value  giá trị lớn nhất của dữ liệu
     */
    public static function niceMax(int $value): int
    {
        if ($value <= 0) {
            return 10;
        }
        $magnitude = 10 ** (int) floor(log10($value));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($value <= $step * $magnitude) {
                return (int) ($step * $magnitude);
            }
        }

        return 10 * $magnitude;
    }

    /**
     * Số gọn cho nhãn trục: 1.500 → "1,5K"; 2.000.000 → "2Tr"; 3 tỷ → "3Tỷ".
     *
     * @param  int  $value  giá trị
     */
    public static function compact(int $value): string
    {
        return match (true) {
            $value >= 1_000_000_000 => self::trim($value / 1_000_000_000).'Tỷ',
            $value >= 1_000_000 => self::trim($value / 1_000_000).'Tr',
            $value >= 1_000 => self::trim($value / 1_000).'K',
            default => (string) $value,
        };
    }

    /**
     * Một chữ số thập phân, bỏ ",0": 1.5 → "1,5"; 2.0 → "2".
     *
     * @param  float  $value  giá trị đã chia đơn vị
     */
    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }

    /**
     * Cách nhóm theo độ dài khoảng thời gian.
     *
     * @param  CarbonInterface  $from  đầu khoảng
     * @param  CarbonInterface  $to  cuối khoảng
     * @return string self::MODE_*
     */
    private static function mode(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = $from->diffInDays($to);

        return match (true) {
            $days <= self::DAILY_MAX_DAYS => self::MODE_DAY,
            $days <= self::WEEKLY_MAX_DAYS => self::MODE_WEEK,
            default => self::MODE_MONTH,
        };
    }

    /**
     * Đầu nhóm chứa thời điểm.
     *
     * @param  CarbonImmutable  $date  thời điểm
     * @param  string  $mode  self::MODE_*
     */
    private static function bucketStart(CarbonImmutable $date, string $mode): CarbonImmutable
    {
        return match ($mode) {
            self::MODE_DAY => $date->startOfDay(),
            self::MODE_WEEK => $date->startOfWeek(CarbonInterface::MONDAY),
            self::MODE_MONTH => $date->startOfMonth(),
        };
    }

    /**
     * Khóa của nhóm bắt đầu tại $start.
     *
     * @param  CarbonImmutable  $start  đầu nhóm
     * @param  string  $mode  self::MODE_*
     */
    private static function key(CarbonImmutable $start, string $mode): string
    {
        return $mode === self::MODE_MONTH ? $start->format('Y-m') : $start->format('Y-m-d');
    }
}
