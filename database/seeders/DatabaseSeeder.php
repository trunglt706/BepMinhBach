<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Dữ liệu demo chỉ dành cho local/testing — không bao giờ chạy trên production.
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('Bỏ qua: dữ liệu demo chỉ được tạo ở môi trường local/testing. Dùng `php artisan bmb:create-admin` để tạo tài khoản quản trị.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
