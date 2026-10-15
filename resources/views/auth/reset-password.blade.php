<x-layouts.guest title="Đặt mật khẩu mới">
    <h1 class="h3 mb-1">Đặt mật khẩu mới</h1>
    <p class="text-body-secondary mb-4">Mật khẩu tối thiểu 8 ký tự. Sau khi đặt lại, mọi máy đang đăng nhập tài khoản này sẽ bị đăng xuất.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate class="card card-body p-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" label="Email" type="email" :value="$email" required autocomplete="username" inputmode="email" />
        <x-input name="password" label="Mật khẩu mới" type="password" required autocomplete="new-password" minlength="8" autofocus />
        <x-input name="password_confirmation" label="Nhập lại mật khẩu mới" type="password" required autocomplete="new-password" minlength="8" />
        <button type="submit" class="btn btn-primary btn-lg w-100" data-loading-text="Đang lưu…">Lưu mật khẩu mới</button>
    </form>

    <p class="text-center small mt-4 mb-0">
        <a href="{{ route('password.request') }}" class="fw-semibold">Link hết hạn? Gửi lại link</a>
        · <a href="{{ route('login') }}">Đăng nhập</a>
    </p>
</x-layouts.guest>
