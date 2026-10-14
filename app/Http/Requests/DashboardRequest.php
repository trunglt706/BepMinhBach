<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Khoảng thời gian của trang tổng quan (?days=7|30|90|365). Giá trị lạ → mặc định, không báo lỗi. */
class DashboardRequest extends FormRequest
{
    /** Các khoảng thời gian cho bộ chọn: số ngày => nhãn. */
    public const PERIODS = [7 => '7 ngày qua', 30 => '30 ngày qua', 90 => '90 ngày qua', 365 => '12 tháng qua'];

    /** Khoảng mặc định (ngày). */
    public const DEFAULT_DAYS = 30;

    /**
     * Mọi tài khoản đã đăng nhập (route đã có middleware auth).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Giá trị ngoài danh sách được thay bằng mặc định.
     */
    protected function prepareForValidation(): void
    {
        $days = (int) $this->query('days', self::DEFAULT_DAYS);

        $this->merge(['days' => array_key_exists($days, self::PERIODS) ? $days : self::DEFAULT_DAYS]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['days' => ['required', 'integer', 'in:'.implode(',', array_keys(self::PERIODS))]];
    }

    /**
     * Số ngày đã chọn.
     */
    public function days(): int
    {
        return (int) $this->validated('days');
    }
}
