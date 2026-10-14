<?php

namespace App\Http\Requests;

use App\Enums\BatchStatus;
use App\Support\BatchFilter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

/**
 * Bộ lọc đợt nấu (danh sách đợt + báo cáo): từ khóa, trạng thái, khoảng ngày.
 * Giá trị không hợp lệ bị bỏ qua thay vì báo lỗi, để người dùng luôn thấy được trang;
 * "từ ngày" sau "đến ngày" thì tự đổi chỗ.
 */
class BatchFilterRequest extends FormRequest
{
    /** Định dạng ngày của ô lọc (input type=date). */
    public const DATE_FORMAT = 'Y-m-d';

    /** Độ dài tối đa của từ khóa. */
    public const MAX_KEYWORD = 100;

    /**
     * Quyền xem trang được kiểm tra ở controller (Policy/Gate tương ứng).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Chuẩn hóa: cắt từ khóa, bỏ trạng thái/ngày không hợp lệ, đổi chỗ khoảng ngày bị ngược.
     */
    protected function prepareForValidation(): void
    {
        $from = $this->parseDate($this->query('from'));
        $to = $this->parseDate($this->query('to'));
        if ($from && $to && $to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $q = trim((string) $this->query('q', ''));

        $this->merge([
            'q' => $q !== '' ? mb_substr($q, 0, self::MAX_KEYWORD) : null,
            'status' => is_string($this->query('status')) ? BatchStatus::tryFrom($this->query('status'))?->value : null,
            'from' => $from?->format(self::DATE_FORMAT),
            'to' => $to?->format(self::DATE_FORMAT),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.self::MAX_KEYWORD],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date_format:'.self::DATE_FORMAT],
            'to' => ['nullable', 'date_format:'.self::DATE_FORMAT],
        ];
    }

    /**
     * Bộ lọc đã kiểm tra, dùng để áp vào truy vấn và mô tả phạm vi báo cáo.
     */
    public function filter(): BatchFilter
    {
        $data = $this->validated();

        return new BatchFilter(
            q: $data['q'] ?? null,
            status: BatchStatus::tryFrom((string) ($data['status'] ?? '')),
            from: $this->parseDate($data['from'] ?? null),
            to: $this->parseDate($data['to'] ?? null),
        );
    }

    /**
     * Đọc ngày dạng Y-m-d; sai định dạng hoặc ngày không tồn tại → null.
     *
     * @param  mixed  $value  giá trị từ URL
     */
    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $value);

            return $date && $date->format(self::DATE_FORMAT) === $value ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }
}
