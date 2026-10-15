<?php

namespace App\Support;

/**
 * Mô tả ngắn chuỗi User-Agent cho danh sách "Thiết bị đã đăng nhập": trình duyệt + hệ điều hành + loại máy.
 * Chỉ nhận dạng các trường hợp phổ biến; không rõ thì ghi "Không rõ". Không dùng để phân quyền.
 */
class UserAgent
{
    /**
     * @param  string|null  $ua  chuỗi User-Agent
     * @return array{browser: string, os: string, mobile: bool, label: string}
     */
    public static function describe(?string $ua): array
    {
        $ua = (string) $ua;

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'CocCoc') || str_contains($ua, 'coc_coc') => 'Cốc Cốc',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Zalo') => 'Zalo',
            str_contains($ua, 'FBAN') || str_contains($ua, 'FBAV') => 'Facebook',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Trình duyệt không rõ',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'hệ điều hành không rõ',
        };

        $mobile = (bool) preg_match('/Mobile|iPhone|Android|iPad/i', $ua);

        return ['browser' => $browser, 'os' => $os, 'mobile' => $mobile, 'label' => $browser.' trên '.$os];
    }
}
