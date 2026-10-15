<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            // Không bắt buộc thuộc chương trình. Chương trình bị xóa (chỉ chương trình nháp) thì bài viết vẫn giữ, chỉ bỏ liên kết.
            $table->foreignId('cooking_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
