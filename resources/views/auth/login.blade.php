<x-layouts.guest title="Đăng nhập">
    <h1 class="h3 mb-1">Đăng nhập</h1>
    <p class="text-body-secondary mb-4">Dùng email và mật khẩu quản trị viên đã cấp cho bạn.</p>

    @if (session('status'))
        <div class="alert alert-info" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i><div>{{ session('status') }}</div></div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" novalidate class="card card-body p-4">
        @csrf
        <x-input name="email" label="Email" type="email" required autocomplete="username" autofocus inputmode="email" placeholder="ten@vidu.com" />
        <div class="mb-3">
            <label for="f_password" class="form-label">Mật khẩu <span class="required-mark" aria-hidden="true">*</span></label>
            <div class="input-group">
                <input type="password" id="f_password" name="password" required autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror" @error('password') aria-describedby="f_password_error" @enderror>
                <button type="button" class="btn btn-outline-secondary" data-toggle-password="f_password" aria-label="Hiện mật khẩu" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')<div id="f_password_error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Ghi nhớ đăng nhập trên máy này</label>
            </div>
            <a href="{{ route('password.request') }}" class="small fw-semibold">Quên mật khẩu?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100" data-loading-text="Đang đăng nhập…">Đăng nhập</button>
    </form>

    <p class="text-center small text-body-secondary mt-4 mb-0">
        <i class="bi bi-question-circle" aria-hidden="true"></i>
        Chưa có tài khoản? Liên hệ quản trị viên Góp Gió. Không dùng “Ghi nhớ đăng nhập” trên máy dùng chung.
    </p>
    <p class="text-center small mt-2 mb-0">
        <a href="{{ route('blog.index') }}" class="fw-semibold"><i class="bi bi-newspaper" aria-hidden="true"></i> Xem bài viết về các chương trình</a>
    </p>
</x-layouts.guest>
