<x-layouts.public title="Liên hệ" description="Gửi lời nhắn cho đội bếp BếpMinhBạch: đăng ký tình nguyện, ủng hộ nguyên liệu, hợp tác hoặc góp ý."
                  :canonical="route('contact.create')">
    <x-page-header title="Liên hệ" subtitle="Gửi lời nhắn cho đội bếp — chúng tôi sẽ liên lạc lại qua email hoặc số điện thoại bạn để lại." />

    <div class="row g-4">
        <div class="col-lg-8">
            @if(session('success'))
                <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if($sentToday && ! $errors->any())
                {{-- Đã gửi hôm nay: không hiện form (máy chủ vẫn chặn nếu cố gửi lại). --}}
                <div class="card">
                    <x-empty-state icon="bi-envelope-check" title="Bạn đã gửi liên hệ hôm nay">
                        Mỗi người có thể gửi một lời nhắn mỗi ngày để đội bếp kịp đọc và phản hồi. Bạn có thể gửi thêm vào ngày mai.
                        <x-slot:action><a href="{{ route('blog.index') }}" class="btn btn-outline-primary">Xem bài viết</a></x-slot:action>
                    </x-empty-state>
                </div>
            @else
                <form method="POST" action="{{ route('contact.store') }}" class="card card-body p-4" novalidate>
                    @csrf
                    @error('limit')
                        <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
                            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                            <div>{{ $message }}</div>
                        </div>
                    @enderror

                    <fieldset class="mb-3">
                        <legend class="form-label fw-semibold fs-6">Chủ đề <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden">(bắt buộc)</span></legend>
                        @php($current = old('topic', $selected->value))
                        <div class="topic-options">
                            @foreach($topics as $t)
                                <label class="topic-option">
                                    <input type="radio" name="topic" value="{{ $t->value }}" class="topic-radio"
                                           data-topic-hint="{{ $t->hint() }}" @checked($current === $t->value) required>
                                    <span class="topic-option-body"><i class="bi {{ $t->icon() }}" aria-hidden="true"></i> {{ $t->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('topic')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </fieldset>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-input name="name" label="Họ tên" required maxlength="100" autocomplete="name" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="contact" label="Email hoặc số điện thoại" required maxlength="150" autocomplete="email" data-validate="email-or-phone"
                                     help="Chỉ dùng để đội bếp liên lạc lại, không công khai." />
                        </div>
                    </div>

                    @php($hint = \App\Enums\ContactTopic::tryFrom((string) $current)?->hint() ?? $selected->hint())
                    <x-textarea name="message" label="Nội dung" required rows="5" minlength="10" maxlength="2000" :help="$hint" id="f_message" />

                    {{-- Bẫy bot: người dùng không thấy, không tab tới được. --}}
                    <div class="contact-trap" aria-hidden="true">
                        <label for="f_website">Website</label>
                        <input type="text" id="f_website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <p class="small text-body-secondary mb-3">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        Vui lòng không ghi thông tin sức khỏe hay thông tin riêng của người nhận suất ăn. Mỗi người gửi được một liên hệ mỗi ngày.
                    </p>
                    <div>
                        <button type="submit" class="btn btn-primary" data-loading-text="Đang gửi…"><i class="bi bi-send" aria-hidden="true"></i> Gửi liên hệ</button>
                    </div>
                </form>
            @endif
        </div>

        <aside class="col-lg-4">
            <div class="card card-body">
                <h2 class="h6 fw-bold mb-3">Bạn có thể liên hệ về</h2>
                <ul class="list-unstyled mb-0 d-grid gap-2">
                    @foreach($topics as $t)
                        @continue($t === \App\Enums\ContactTopic::Other)
                        <li class="d-flex gap-2"><i class="bi {{ $t->icon() }} text-primary" aria-hidden="true"></i> <span>{{ $t->label() }}</span></li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.public>
