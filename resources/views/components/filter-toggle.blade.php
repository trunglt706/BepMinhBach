@props(['target', 'active' => false])
{{-- Nút phễu (chỉ trên điện thoại): bấm thì nút ẩn và bộ lọc #target hiện ra.
     app.js bỏ "hidden" và thu gọn bộ lọc; không có JS thì bộ lọc luôn hiện, nút không xuất hiện. --}}
<button type="button" class="btn btn-outline-secondary btn-icon d-md-none position-relative" hidden
        data-filter-toggle="{{ $target }}" aria-controls="{{ $target }}" aria-expanded="false" aria-label="Hiện bộ lọc">
    <i class="bi bi-funnel" aria-hidden="true"></i>
    @if($active)<span class="filter-dot" aria-hidden="true"></span><span class="visually-hidden">(đang lọc)</span>@endif
</button>
