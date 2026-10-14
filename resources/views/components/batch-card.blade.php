@props(['batch'])
{{-- Thẻ đợt nấu dùng ở danh sách và tổng quan. Cần withTaskProgress() + coordinator. --}}
<a href="{{ route('batches.show', $batch) }}" {{ $attributes->class(['batch-card']) }}>
    <div class="batch-card-body">
        <x-date-tile :date="$batch->starts_at" />
        <div class="min-w-0 flex-grow-1">
            <div class="mb-1"><x-status-badge :status="$batch->status" /></div>
            <h2 class="batch-card-title">{{ $batch->name }}</h2>
            <ul class="meta-list">
                <li><i class="bi bi-clock" aria-hidden="true"></i> {{ $batch->starts_at->format('H:i') }} · {{ $batch->starts_at->format('d/m/Y') }}</li>
                @if($batch->location_text)
                    <li><i class="bi bi-geo-alt" aria-hidden="true"></i> <span class="text-truncate">{{ $batch->location_text }}</span></li>
                @endif
                <li><i class="bi bi-person" aria-hidden="true"></i> {{ $batch->coordinator?->name }}</li>
            </ul>
        </div>
    </div>
    <div class="batch-card-footer">
        <div class="d-flex justify-content-between align-items-center small mb-2">
            <span><i class="bi bi-box2-heart text-body-secondary" aria-hidden="true"></i>
                <strong class="tabular">{{ App\Support\Format::number($batch->expected_servings) }}</strong> suất dự kiến</span>
        </div>
        <x-task-progress :done="$batch->tasks_done_count" :total="$batch->tasks_count" />
    </div>
</a>
