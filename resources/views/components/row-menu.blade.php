@props(['label', 'editUrl' => null, 'editLabel' => 'Sửa', 'deleteUrl' => null, 'confirm' => null])
{{-- Menu "⋮" gom thao tác quản lý ít dùng (sửa/xóa) để thao tác chính của dòng nổi bật hơn. --}}
<div class="dropdown kebab d-print-none">
    <button type="button" class="btn btn-sm btn-icon btn-link text-body-secondary" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Thao tác khác: {{ $label }}">
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @if($editUrl)
            <li><a class="dropdown-item" href="{{ $editUrl }}" data-modal><i class="bi bi-pencil" aria-hidden="true"></i> {{ $editLabel }}</a></li>
        @endif
        {{ $slot }}
        @if($deleteUrl)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ $deleteUrl }}" data-ajax data-confirm="{{ $confirm ?? 'Xóa “'.$label.'”? Thao tác không thể hoàn tác.' }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash" aria-hidden="true"></i> Xóa</button>
                </form>
            </li>
        @endif
    </ul>
</div>
