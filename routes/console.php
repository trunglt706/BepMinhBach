<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// Tạo tài khoản quản trị đầu tiên trên môi trường thật (mật khẩu nhập ẩn, không ghi log).
Artisan::command('bmb:create-admin', function () {
    $name = $this->ask('Họ tên');
    $email = mb_strtolower(trim((string) $this->ask('Email đăng nhập')));
    $password = $this->secret('Mật khẩu (tối thiểu 10 ký tự)');
    $confirmation = $this->secret('Nhập lại mật khẩu');

    $validator = Validator::make(
        ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
        [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ],
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    User::create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => Role::Admin,
        'is_active' => true,
    ]);

    $this->info('Đã tạo tài khoản quản trị.');

    return 0;
})->purpose('Tạo tài khoản quản trị BếpMinhBạch');
