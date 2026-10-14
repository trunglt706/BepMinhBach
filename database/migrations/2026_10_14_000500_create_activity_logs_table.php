<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lịch sử tối thiểu cho thay đổi quan trọng (trạng thái, số suất, chi phí, tài khoản).
        // Chỉ lưu tóm tắt an toàn, không lưu payload đầy đủ.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('summary', 500);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
