<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// Tạo tài khoản quản trị đầu tiên trên môi trường thật (mật khẩu nhập ẩn, không ghi log).
// Chạy không tương tác (script triển khai): --name, --email và mật khẩu qua biến môi trường BMB_ADMIN_PASSWORD
// (không truyền mật khẩu bằng tham số dòng lệnh vì sẽ hiện trong danh sách tiến trình).
Artisan::command('bmb:create-admin {--name= : Họ tên} {--email= : Email đăng nhập}', function () {
    $envPassword = (string) getenv('BMB_ADMIN_PASSWORD');
    $name = $this->option('name') ?: $this->ask('Họ tên');
    $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email đăng nhập'))));
    $password = $envPassword !== '' ? $envPassword : $this->secret('Mật khẩu (tối thiểu 8 ký tự)');
    $confirmation = $envPassword !== '' ? $envPassword : $this->secret('Nhập lại mật khẩu');

    $validator = Validator::make(
        ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
        [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
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
})->purpose('Tạo tài khoản quản trị Góp Gió');

// Dọn dẹp định kỳ (cron "schedule:run" mỗi phút do deploy.sh tạo trong /etc/cron.d/gopgio):
// token đặt lại mật khẩu đã hết hạn, job gửi thông báo lỗi quá 30 ngày.
Schedule::command('auth:clear-resets')->dailyAt('03:10');
Schedule::command('queue:prune-failed', ['--hours' => 720])->weeklyOn(1, '03:20');
