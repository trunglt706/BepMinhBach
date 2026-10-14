<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kênh thông báo của từng đợt nấu (Slack / Telegram / Chatwork).
 * settings: thông số kết nối, MÃ HÓA bằng APP_KEY (token, webhook). join_url: link để mọi người tham gia kênh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_batch_id')->constrained()->cascadeOnDelete();
            $table->string('driver', 20);
            $table->text('settings');
            $table->string('join_url', 500)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cooking_batch_id', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_notification_channels');
    }
};
