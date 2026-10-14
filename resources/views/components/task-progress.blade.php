@props(['done' => 0, 'total' => 0])
@php($percent = $total > 0 ? (int) round($done / $total * 100) : 0)
<div {{ $attributes }}>
    @if($total === 0)
        <span class="small text-body-secondary"><i class="bi bi-list-task" aria-hidden="true"></i> Chưa có công việc</span>
    @else
        <div class="d-flex justify-content-between small mb-1">
            <span><span class="fw-semibold">Công việc: {{ $done }}/{{ $total }}</span> đã xong</span>
            <span class="fw-semibold tabular">{{ $percent }}%</span>
        </div>
        <div class="progress" role="progressbar" aria-label="Tiến độ công việc" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
            <div class="progress-bar" style="width: {{ $percent }}%"></div>
        </div>
    @endif
</div>
