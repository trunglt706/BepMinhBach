<x-layouts.app title="Đợt nấu ăn">
    <x-page-header title="Đợt nấu ăn" :subtitle="$batches->total() > 0 ? 'Tìm thấy '.$batches->total().' đợt nấu.' : null">
        @can('create', App\Models\CookingBatch::class)
            <x-slot:actions>
                <a href="{{ route('batches.create') }}" data-modal class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tạo đợt nấu</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    @include('partials.batch-filter', ['action' => route('batches.index'), 'submitLabel' => 'Tìm', 'statusMode' => 'chips'])

    @if($batches->isEmpty())
        <div class="card">
            @if($filter->isEmpty())
                <x-empty-state icon="bi-calendar-plus" title="Chưa có đợt nấu ăn nào">
                    Tạo đợt nấu để lên kế hoạch số suất, nguyên liệu và phân công việc.
                    @can('create', App\Models\CookingBatch::class)
                        <x-slot:action>
                            <a href="{{ route('batches.create') }}" data-modal class="btn btn-primary">Tạo đợt nấu đầu tiên</a>
                        </x-slot:action>
                    @endcan
                </x-empty-state>
            @else
                <x-empty-state icon="bi-search" title="Không tìm thấy đợt nấu phù hợp">
                    Thử bỏ bớt điều kiện lọc.
                    <x-slot:action><a href="{{ route('batches.index') }}" class="btn btn-outline-secondary">Xóa bộ lọc</a></x-slot:action>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="row g-3" data-skeleton="cards">
            @foreach($batches as $batch)
                <div class="col-md-6 col-xxl-4">
                    <x-batch-card :batch="$batch" />
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $batches->links() }}</div>
    @endif
</x-layouts.app>
