<x-form-shell title="Tạo tài khoản" :action="route('users.store')"
              :back="route('users.index')" back-label="Quản lý tài khoản" bare>
    @include('users._form')
    <x-slot:actions>
        <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus" aria-hidden="true"></i> Tạo tài khoản</button>
    </x-slot:actions>
</x-form-shell>
