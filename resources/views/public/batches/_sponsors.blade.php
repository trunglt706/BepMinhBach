{{--
    Đơn vị tham gia chương trình — băng trượt ngang (theo thứ tự phân loại), mỗi thẻ dẫn tới trang riêng của đơn vị.
    Không JS: vuốt/cuộn ngang. Có JS (app.js, data-carousel): nút trước/sau, ẩn nút khi không cần cuộn. Không tự chạy.
    Biến: $groups (đơn vị nhóm theo SponsorType::value).
--}}
@if($groups->isNotEmpty())
    @php
        $sponsors = collect(App\Enums\SponsorType::cases())->flatMap(fn ($type) => $groups->get($type->value, collect()));
    @endphp
    <section class="sponsor-carousel mb-4" aria-labelledby="batch-sponsors-title" data-carousel>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h2 id="batch-sponsors-title" class="h5 fw-bold mb-0"><i class="bi bi-award" aria-hidden="true"></i> Đơn vị tham gia
                <span class="small text-body-secondary fw-normal">({{ $sponsors->count() }})</span></h2>
            <div class="sponsor-carousel-nav" hidden data-carousel-nav>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" data-carousel-prev aria-label="Đơn vị trước"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" data-carousel-next aria-label="Đơn vị tiếp theo"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
        <ul class="sponsor-carousel-track list-unstyled mb-0" data-carousel-track>
            @foreach($sponsors as $s)
                <li class="sponsor-carousel-item">
                    <a href="{{ route('sponsors.public.show', $s) }}" class="card sponsor-card h-100">
                        <x-sponsor-logo :sponsor="$s" size="lg" />
                        <span class="sponsor-card-body">
                            <span class="small text-body-secondary d-block"><i class="bi {{ $s->type->icon() }}" aria-hidden="true"></i> {{ $s->type->label() }}</span>
                            <span class="fw-semibold d-block">{{ $s->name }}</span>
                            @if($s->pivot->contribution)<span class="small text-body-secondary d-block">{{ $s->pivot->contribution }}</span>@endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
