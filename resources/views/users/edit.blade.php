<x-form-shell title="Sửa tài khoản" :subtitle="$user->name"
              :action="route('users.update', $user)" method="PUT"
              :back="route('users.index')" back-label="Quản lý tài khoản" bare>
    @include('users._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Lưu thay đổi</button>
    </x-slot:actions>
</x-form-shell>
