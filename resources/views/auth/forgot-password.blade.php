<x-layouts.guest title="Quên mật khẩu">
    <h1 class="h3 mb-1">Quên mật khẩu</h1>
    <p class="text-body-secondary mb-4">Nhập email đăng nhập. Chúng tôi sẽ gửi link để bạn đặt mật khẩu mới (hết hạn sau 60 phút).</p>

    @if (session('status'))
        <div class="alert alert-success d-flex gap-2" role="status"><i class="bi bi-envelope-check" aria-hidden="true"></i><div>{{ session('status') }}</div></div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate class="card card-body p-4">
        @csrf
        <x-input name="email" label="Email" type="email" required autocomplete="username" autofocus inputmode="email" placeholder="ten@vidu.com" />
        <button type="submit" class="btn btn-primary btn-lg w-100" data-loading-text="Đang gửi…">Gửi link đặt lại mật khẩu</button>
    </form>

    <p class="text-center small text-body-secondary mt-4 mb-0">
        Không nhận được email sau vài phút? Kiểm tra mục Spam, hoặc liên hệ quản trị viên Góp Gió để được cấp lại mật khẩu.
    </p>
    <p class="text-center small mt-2 mb-0">
        <a href="{{ route('login') }}" class="fw-semibold"><i class="bi bi-arrow-left" aria-hidden="true"></i> Quay lại đăng nhập</a>
    </p>
</x-layouts.guest>
