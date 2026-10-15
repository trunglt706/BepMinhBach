@props(['status'])
@php
    use App\Enums\BatchStatus;
    $steps = [
        BatchStatus::Draft->value => 'Nháp',
        BatchStatus::Planned->value => 'Kế hoạch',
        BatchStatus::InProgress->value => 'Đang diễn ra',
        BatchStatus::Completed->value => 'Hoàn tất',
    ];
    $current = array_search($status->value, array_keys($steps), true);
@endphp
{{-- Thể hiện vị trí trong quy trình bằng cả số thứ tự, chữ và biểu tượng (không chỉ dựa vào màu). --}}
<ol {{ $attributes->class(['stepper']) }} aria-label="Tiến trình chương trình">
    @foreach(array_values($steps) as $i => $label)
        @php($state = $i < $current || $status === BatchStatus::Completed ? 'is-done' : ($i === $current ? 'is-current' : ''))
        <li class="{{ $state }}" @if($state === 'is-current') aria-current="step" @endif>
            <span class="step-dot">
                @if($state === 'is-done')<i class="bi bi-check-lg" aria-hidden="true"></i>@else{{ $i + 1 }}@endif
            </span>
            <span>{{ $label }}<span class="visually-hidden">{{ $state === 'is-done' ? ' (đã qua)' : ($state === 'is-current' ? ' (hiện tại)' : '') }}</span></span>
        </li>
    @endforeach
</ol>
