@php use App\Support\Format; @endphp
{{--
    Slider đầu trang chủ: các chương trình ĐANG DIỄN RA. Mỗi slide là một liên kết tới trang chương trình công khai.
    Không JS: dải trượt ngang (vuốt/cuộn), mọi slide vẫn bấm được. Có JS (app.js, data-slider): nút trước/sau,
    chấm chỉ vị trí, tự chuyển 6 giây (dừng khi rê chuột/focus/chạm, tắt khi người dùng chọn giảm chuyển động).
    Ảnh slide đầu tải ngay (ảnh lớn nhất màn hình đầu); slide khác chỉ tải khi sắp hiện.
--}}
<section class="batch-slider mb-4" aria-roledescription="carousel" aria-label="Chương trình đang diễn ra" data-slider>
    <div class="batch-slider-track" data-slider-track tabindex="-1">
        @foreach($batches as $b)
            @php($cover = $b->coverImage())
            <a href="{{ route('blog.batch', $b) }}" class="batch-slide" data-slide
               aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ $loop->count }}: {{ $b->name }}">
                <span @class(['batch-slide-media', 'is-placeholder' => ! $cover])>
                    @if($cover)
                        <x-post-img :image="$cover" :width="960" class="batch-slide-img" :priority="$loop->first"
                                    sizes="(min-width: 1400px) 1320px, 100vw" />
                    @endif
                </span>
                <span class="batch-slide-body">
                    <span class="batch-live"><span class="batch-live-dot" aria-hidden="true"></span> Đang diễn ra</span>
                    <span class="batch-slide-title">{{ $b->name }}</span>
                    <span class="batch-slide-meta">
                        <span><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $b->starts_on->toDateString() }}">{{ Format::date($b->starts_on) }}</time></span>
                        @if($b->expected_servings)<span><i class="bi bi-box-seam" aria-hidden="true"></i> {{ Format::number($b->expected_servings) }} suất dự kiến</span>@endif
                    </span>
                    <span class="batch-slide-cta">Xem chương trình <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </span>
            </a>
        @endforeach
    </div>
    @if($batches->count() > 1)
        <button type="button" class="batch-slider-nav is-prev" data-slider-prev aria-label="Chương trình trước"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <button type="button" class="batch-slider-nav is-next" data-slider-next aria-label="Chương trình tiếp theo"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        <div class="batch-slider-dots" role="group" aria-label="Chọn chương trình">
            @foreach($batches as $b)
                <button type="button" data-slider-dot="{{ $loop->index }}" aria-label="Chương trình {{ $loop->iteration }}: {{ $b->name }}"
                        @if($loop->first) aria-current="true" @endif></button>
            @endforeach
        </div>
    @endif
</section>
