<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Nội dung giờ là HTML đã lọc (trình soạn thảo); có thể dài hơn 64 KB.
            $table->mediumText('body')->change();
            // Bản chữ thuần của nội dung: dùng cho tìm kiếm, tóm tắt, mô tả SEO (không khớp nhầm thẻ HTML).
            $table->mediumText('body_text')->nullable()->after('body');
            $table->unsignedBigInteger('view_count')->default(0)->after('published_at');
        });

        Schema::create('post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // cover | gallery
            // Đường dẫn gốc không có đuôi, ví dụ "posts/2026/10/9f1c…"; file thật: "{path}-{width}.webp".
            $table->string('path', 191);
            $table->json('widths');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('caption', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'kind', 'sort_order']);
        });

        // Bài cũ (chữ thuần, đoạn cách nhau bằng dòng trống) → HTML <p>, nội dung được escape.
        DB::table('posts')->orderBy('id')->each(function ($post) {
            $text = (string) $post->body;
            $html = str_contains($text, '<')
                ? $text
                : collect(preg_split('/\R{2,}/u', trim($text)))
                    ->filter(fn ($p) => trim($p) !== '')
                    ->map(fn ($p) => '<p>'.nl2br(e(trim($p)), false).'</p>')
                    ->implode('');
            DB::table('posts')->where('id', $post->id)->update([
                'body' => $html,
                'body_text' => trim(preg_replace('/\s+/u', ' ', $text)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_images');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['body_text', 'view_count']);
        });
    }
};
