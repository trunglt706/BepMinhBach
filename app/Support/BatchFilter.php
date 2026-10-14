<?php

namespace App\Support;

use App\Enums\BatchStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Bộ lọc đợt nấu dùng chung cho danh sách và báo cáo.
 * Giá trị không hợp lệ bị bỏ qua thay vì gây lỗi, để người dùng luôn thấy được trang.
 */
class BatchFilter
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?BatchStatus $status = null,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $q = trim((string) $request->query('q', ''));

        $from = self::parseDate($request->query('from'));
        $to = self::parseDate($request->query('to'));
        if ($from && $to && $to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            q: $q !== '' ? mb_substr($q, 0, 100) : null,
            status: is_string($request->query('status')) ? BatchStatus::tryFrom($request->query('status')) : null,
            from: $from,
            to: $to,
        );
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->q, fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($this->q, '%_\\').'%'))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status->value))
            ->when($this->from, fn (Builder $q) => $q->where('starts_at', '>=', $this->from->startOfDay()))
            ->when($this->to, fn (Builder $q) => $q->where('starts_at', '<=', $this->to->endOfDay()));
    }

    public function isEmpty(): bool
    {
        return ! $this->q && ! $this->status && ! $this->from && ! $this->to;
    }

    /** Mô tả phạm vi dữ liệu cho báo cáo. */
    public function describe(): string
    {
        $parts = [];
        $parts[] = match (true) {
            $this->from && $this->to => 'Từ '.$this->from->format('d/m/Y').' đến '.$this->to->format('d/m/Y'),
            (bool) $this->from => 'Từ '.$this->from->format('d/m/Y'),
            (bool) $this->to => 'Đến '.$this->to->format('d/m/Y'),
            default => 'Mọi thời gian',
        };
        $parts[] = $this->status ? 'Trạng thái: '.$this->status->label() : 'Mọi trạng thái';
        if ($this->q) {
            $parts[] = 'Tên chứa: “'.$this->q.'”';
        }

        return implode(' · ', $parts);
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
