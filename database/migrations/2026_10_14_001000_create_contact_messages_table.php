<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liên hệ gửi từ trang công khai. Không lưu IP thô: client_hash = HMAC(IP) với APP_KEY,
 * chỉ dùng để giới hạn 1 liên hệ / client / ngày.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 30);
            $table->string('name', 100);
            $table->string('contact', 150);               // email hoặc số điện thoại để đội bếp liên lạc lại
            $table->text('message');
            $table->char('client_hash', 64);
            $table->date('sent_on');                      // ngày gửi (giờ VN) — kiểm tra 1 liên hệ / ngày
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_hash', 'sent_on']);
            $table->index(['contact', 'sent_on']);
            $table->index(['handled_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
