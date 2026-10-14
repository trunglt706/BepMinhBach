<?php

namespace App\Support;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bộ lọc đợt nấu đã chuẩn hóa (tạo từ App\Http\Requests\BatchFilterRequest::filter()),
 * dùng chung cho danh sách đợt và báo cáo.
 */
class BatchFilter
{
    /**
     * @param  string|null  $q  từ khóa trong tên đợt
     * @param  BatchStatus|null  $status  trạng thái cần lọc
     * @param  CarbonImmutable|null  $from  từ ngày (tính cả ngày)
     * @param  CarbonImmutable|null  $to  đến ngày (tính cả ngày)
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?BatchStatus $status = null,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
    ) {}

    /**
     * Áp bộ lọc vào truy vấn đợt nấu.
     *
     * @param  Builder<CookingBatch>  $query
     * @return Builder<CookingBatch>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->q, fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($this->q, '%_\\').'%'))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status->value))
            ->when($this->from, fn (Builder $q) => $q->where('starts_at', '>=', $this->from->startOfDay()))
            ->when($this->to, fn (Builder $q) => $q->where('starts_at', '<=', $this->to->endOfDay()));
    }

    /**
     * Không có điều kiện lọc nào.
     */
    public function isEmpty(): bool
    {
        return ! $this->q && ! $this->status && ! $this->from && ! $this->to;
    }

    /**
     * Mô tả phạm vi dữ liệu cho báo cáo ("Từ … đến … · Trạng thái … · Tên chứa …").
     */
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
}
