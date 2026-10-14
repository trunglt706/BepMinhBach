<x-form-shell title="Đổi mật khẩu" subtitle="Nên dùng mật khẩu dài, dễ nhớ với bạn nhưng khó đoán với người khác."
              :action="route('account.password.update')" method="PUT"
              :back="route('dashboard')" back-label="Tổng quan">
    <x-input name="current_password" label="Mật khẩu hiện tại" type="password" required autocomplete="current-password" />
    <x-input name="password" label="Mật khẩu mới" type="password" required autocomplete="new-password" help="Tối thiểu 8 ký tự." />
    <x-input name="password_confirmation" label="Nhập lại mật khẩu mới" type="password" required autocomplete="new-password" />
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-key" aria-hidden="true"></i> Lưu mật khẩu mới</button>
    </x-slot:actions>
</x-form-shell>
