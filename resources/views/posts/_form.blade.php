@php
    $maxAlbum = (int) config('bmb.post_images.max_album');
    $maxMb = (int) round(config('bmb.post_images.max_upload_kb') / 1024);
    $accept = implode(',', config('bmb.post_images.mimes'));
    $gallery = $post->relationLoaded('gallery') ? $post->gallery : collect();
    $cover = $post->relationLoaded('cover') ? $post->cover : null;
    $remaining = max(0, $maxAlbum - $gallery->count());
    $tagValue = old('tags', $post->relationLoaded('tags') ? $post->tags->pluck('name')->implode(', ') : '');
@endphp
<section class="card mb-3" aria-labelledby="sec-post">
    <div class="panel-header"><h2 id="sec-post" class="panel-title"><span class="step-number">1</span> Nội dung</h2></div>
    <div class="card-body pb-1">
        <x-input name="title" label="Tiêu đề" :value="$post->title" required maxlength="200"
                 help="Ví dụ: Cảm ơn 30 bạn tình nguyện viên chương trình cơm chay chủ nhật" />
        <x-textarea name="excerpt" label="Tóm tắt" :value="$post->excerpt" rows="2" maxlength="500"
                    help="Không bắt buộc. Hiện ở danh sách bài viết và khi chia sẻ lên mạng xã hội; để trống thì lấy đoạn đầu của nội dung." />

        {{-- Không có JS: ô văn bản thường (nhận HTML đơn giản). Có JS: app.js thay bằng trình soạn thảo Trix. --}}
        <div class="mb-3">
            <label for="f_body" class="form-label fw-semibold">Nội dung <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden">(bắt buộc)</span></label>
            <textarea id="f_body" name="body" rows="10" required data-rich-editor
                      aria-describedby="f_body_help @error('body') f_body_error @enderror"
                      @class(['form-control', 'is-invalid' => $errors->has('body')])>{{ old('body', $post->body) }}</textarea>
            <div id="f_body_help" class="form-text">Dùng thanh công cụ để in đậm, tạo tiêu đề nhỏ, danh sách, trích dẫn, liên kết. Ảnh thêm ở phần “Ảnh” bên dưới. Không ghi thông tin người nhận suất ăn.</div>
            @error('body')<div id="f_body_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

<section class="card mb-3" aria-labelledby="sec-images">
    <div class="panel-header"><h2 id="sec-images" class="panel-title"><span class="step-number">2</span> Ảnh</h2></div>
    <div class="card-body pb-1">
        <div class="callout callout-muted mb-3"><i class="bi bi-shield-check" aria-hidden="true"></i>
            <span>Nhận JPG, PNG, WebP; tối đa {{ $maxMb }} MB/ảnh. Ảnh được nén sang WebP, xóa thông tin vị trí (GPS) trong ảnh.
                Tránh ảnh nhìn rõ mặt người nhận suất ăn nếu chưa được đồng ý.</span></div>

        {{-- Ảnh đại diện --}}
        <div class="mb-4">
            <label for="f_cover" class="form-label fw-semibold">Ảnh đại diện</label>
            <div class="image-picker" data-image-picker>
                <div class="image-picker-preview preview-tile" data-preview @if($cover) data-existing-image @endif>
                    @if($cover)
                        <img src="{{ $cover->url(480) }}" alt="Ảnh đại diện hiện tại" width="240" height="{{ $cover->heightFor(240) }}">
                    @else
                        <span class="image-picker-empty"><i class="bi bi-image" aria-hidden="true"></i> Chưa có ảnh</span>
                    @endif
                </div>
                <div class="min-w-0">
                    <input type="file" id="f_cover" name="cover" accept="{{ $accept }}" data-image-input
                           @class(['form-control', 'is-invalid' => $errors->has('cover')])
                           aria-describedby="f_cover_help @error('cover') f_cover_error @enderror">
                    <div id="f_cover_help" class="form-text">Hiện ở danh sách bài viết và khi chia sẻ link. Nên dùng ảnh ngang.</div>
                    @error('cover')<div id="f_cover_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @if($cover)
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="f_remove_cover">
                            <label class="form-check-label" for="f_remove_cover">Xóa ảnh đại diện</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Album --}}
        <fieldset class="mb-3">
            <legend class="form-label fw-semibold fs-6 mb-1">Album ảnh <span class="small text-body-secondary fw-normal">(tối đa {{ $maxAlbum }} ảnh)</span></legend>
            @if($gallery->isNotEmpty())
                <ul class="album-editor" aria-label="Ảnh album hiện có">
                    @foreach($gallery as $image)
                        <li class="album-editor-item" data-existing-image>
                            <div class="preview-tile">
                                <img src="{{ $image->url(480) }}" alt="Ảnh album {{ $loop->iteration }}" width="160" height="{{ $image->heightFor(160) }}" loading="lazy">
                            </div>
                            <label class="visually-hidden" for="f_caption_{{ $image->id }}">Chú thích ảnh {{ $loop->iteration }}</label>
                            <input type="text" id="f_caption_{{ $image->id }}" name="captions[{{ $image->id }}]" maxlength="200"
                                   value="{{ old('captions.'.$image->id, $image->caption) }}" class="form-control form-control-sm" placeholder="Chú thích (không bắt buộc)">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_images[]" value="{{ $image->id }}" id="f_remove_{{ $image->id }}">
                                <label class="form-check-label small" for="f_remove_{{ $image->id }}">Xóa ảnh này</label>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            <label for="f_gallery" class="form-label small mb-1">Thêm ảnh @if($gallery->isNotEmpty())(còn thêm được {{ $remaining }})@endif</label>
            <input type="file" id="f_gallery" name="gallery[]" accept="{{ $accept }}" multiple data-image-input data-max-files="{{ $remaining }}"
                   @class(['form-control', 'is-invalid' => $errors->has('gallery')]) @disabled($remaining === 0)
                   aria-describedby="f_gallery_help @error('gallery') f_gallery_error @enderror">
            <div id="f_gallery_help" class="form-text">Chọn nhiều ảnh cùng lúc được. Ảnh lớn được thu nhỏ trên máy trước khi gửi để tải nhanh hơn.</div>
            <ul class="album-editor is-new" data-preview-list aria-label="Ảnh sắp thêm"></ul>
            @error('gallery')<div id="f_gallery_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>
    </div>
</section>

<section class="card mb-3" aria-labelledby="sec-link">
    <div class="panel-header"><h2 id="sec-link" class="panel-title"><span class="step-number">3</span> Chương trình và thẻ</h2></div>
    <div class="card-body pb-1">
        <x-select name="cooking_batch_id" label="Thuộc chương trình" :options="$batches" :value="$post->cooking_batch_id"
                  placeholder="— Không thuộc chương trình nào —"
                  help="Nếu chọn, trang bài viết hiện thêm tên, thời gian, trạng thái và số suất của chương trình (không hiện chi phí hay người phụ trách)." />

        {{-- Không có JS: ô chữ, ngăn cách bằng dấu phẩy. Có JS: app.js biến thành ô nhập thẻ (Enter/Tab/dấu phẩy để thêm). --}}
        <div class="mb-3">
            <label for="f_tags" class="form-label fw-semibold">Thẻ</label>
            <input type="text" id="f_tags" name="tags" value="{{ $tagValue }}" maxlength="500" autocomplete="off"
                   list="tag-suggestions" data-tags-input data-max-tags="{{ App\Http\Requests\PostRequest::MAX_TAGS }}"
                   @class(['form-control', 'is-invalid' => $errors->has('tags')])
                   aria-describedby="f_tags_help @error('tags') f_tags_error @enderror">
            <datalist id="tag-suggestions">
                @foreach($tagSuggestions as $name)<option value="{{ $name }}">@endforeach
            </datalist>
            <div id="f_tags_help" class="form-text">Gõ thẻ rồi nhấn Enter, Tab hoặc dấu phẩy. Tối đa {{ App\Http\Requests\PostRequest::MAX_TAGS }} thẻ. Ví dụ: cơm chay, chủ nhật, cảm ơn</div>
            @error('tags')<div id="f_tags_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>
