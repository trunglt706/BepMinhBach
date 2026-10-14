@props([
    'title',
    'subtitle' => null,
    'action',
    'method' => 'POST',
    'back',
    'backLabel' => 'Quay lại',
    'cancelLabel' => 'Hủy',
    'size' => null,
    'bare' => false,
    'files' => false,
])
{{--
    Khung form dùng cho tác vụ ngắn.
    - Mở bình thường (JS tắt, mở tab mới...): trang đầy đủ, gửi form như thường rồi redirect.
    - Mở bằng ?modal=1 (app.js): chỉ trả nội dung modal; form gửi bằng AJAX, lỗi hiện ngay trong modal,
      thành công thì đóng modal và cập nhật vùng dữ liệu trên trang, không tải lại trang.
    - bare: nội dung đã tự chia thành các <section class="card">, không bọc thêm thẻ.
    - files: form có tải file (enctype multipart/form-data).
    - $after: chỉ hiện ở trang đầy đủ, nằm ngoài form (ví dụ vùng xóa) — không lồng form trong modal.
--}}
@php($asModal = request()->boolean('modal'))

@if($asModal)
    <form method="POST" action="{{ $action }}" class="modal-content" data-ajax novalidate @if($files) enctype="multipart/form-data" @endif
          data-modal-size="{{ $size }}">
        @csrf
        @if(strtoupper($method) !== 'POST') @method($method) @endif
        <div class="modal-header">
            <div class="min-w-0">
                <h2 class="modal-title h5" id="bmb-modal-title">{{ $title }}</h2>
                @if($subtitle)<p class="small text-body-secondary mb-0 text-truncate">{{ $subtitle }}</p>@endif
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
        <div class="modal-body">
            <div data-form-alert></div>
            {{ $before ?? '' }}
            {{ $slot }}
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-link text-body-secondary me-auto" data-bs-dismiss="modal">{{ $cancelLabel }}</button>
            {{ $actions }}
        </div>
    </form>
@else
    <x-layouts.app :title="$title">
        <div class="form-narrow mx-auto">
            <x-page-header :title="$title" :subtitle="$subtitle" :back="$back" :back-label="$backLabel" />
            {{ $before ?? '' }}
            <form method="POST" action="{{ $action }}" novalidate @if($files) enctype="multipart/form-data" @endif>
                @csrf
                @if(strtoupper($method) !== 'POST') @method($method) @endif
                @if($bare)
                    {{ $slot }}
                @else
                    <section class="card mb-3"><div class="card-body pb-1">{{ $slot }}</div></section>
                @endif
                <div class="form-actions">
                    {{ $actions }}
                    <a href="{{ $back }}" class="btn btn-link text-body-secondary">{{ $cancelLabel }}</a>
                </div>
            </form>
            {{ $after ?? '' }}
        </div>
    </x-layouts.app>
@endif
