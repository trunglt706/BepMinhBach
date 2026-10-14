<?php

namespace Tests\Unit;

use App\Support\Charts;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ChartsTest extends TestCase
{
    public function test_bucket_size_follows_period_length(): void
    {
        $to = CarbonImmutable::parse('2026-10-14 15:00');

        $days = Charts::buckets($to->subDays(7)->startOfDay(), $to);
        $this->assertCount(8, $days);
        $this->assertSame('07/10', $days[0]['label']);
        $this->assertSame('Ngày 14/10/2026', $days[7]['title']);

        // 90 ngày → theo tuần, bắt đầu thứ Hai; không bỏ trống tuần nào
        $weeks = Charts::buckets($to->subDays(90)->startOfDay(), $to);
        $this->assertCount(14, $weeks);
        $this->assertSame('Tuần 13/07 – 19/07/2026', $weeks[0]['title']);

        $months = Charts::buckets($to->subDays(365)->startOfDay(), $to);
        $this->assertCount(13, $months);
        $this->assertSame('Th10', $months[0]['label']);
        $this->assertSame('Tháng 10/2026', $months[12]['title']);
    }

    public function test_group_by_time_sums_series_and_skips_not_entered(): void
    {
        $to = CarbonImmutable::parse('2026-10-14 15:00');
        $from = $to->subDays(30)->startOfDay();
        $items = collect([
            (object) ['at' => CarbonImmutable::parse('2026-10-13 08:00'), 'plan' => 100, 'done' => 90],
            (object) ['at' => CarbonImmutable::parse('2026-10-12 08:00'), 'plan' => 50, 'done' => null],
            (object) ['at' => CarbonImmutable::parse('2026-01-01 08:00'), 'plan' => 999, 'done' => 999], // ngoài khoảng
        ]);

        $groups = Charts::groupByTime($items, fn ($i) => $i->at, ['plan' => fn ($i) => $i->plan, 'done' => fn ($i) => $i->done], $from, $to);

        $this->assertSame(['plan' => 150, 'done' => 90], end($groups)['values']); // tuần 12/10 – 18/10
        $this->assertSame(150, collect($groups)->sum(fn ($g) => $g['values']['plan']));
    }

    public function test_nice_axis_maximum_and_compact_labels(): void
    {
        $this->assertSame(10, Charts::niceMax(0));
        $this->assertSame(200, Charts::niceMax(130));
        $this->assertSame(250, Charts::niceMax(210));
        $this->assertSame(5_000_000, Charts::niceMax(3_200_000));

        $this->assertSame('120', Charts::compact(120));
        $this->assertSame('1,5K', Charts::compact(1500));
        $this->assertSame('2Tr', Charts::compact(2_000_000));
        $this->assertSame('2,5Tr', Charts::compact(2_500_000));
    }
}
