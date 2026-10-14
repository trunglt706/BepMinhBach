<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ảnh nền đợt nấu (slider trang chủ, trang đợt công khai): các bản WebP giống ảnh bài viết. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('notes');
            $table->json('cover_widths')->nullable()->after('cover_path');
            $table->unsignedInteger('cover_width')->nullable()->after('cover_widths');
            $table->unsignedInteger('cover_height')->nullable()->after('cover_width');
        });
    }

    public function down(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'cover_widths', 'cover_width', 'cover_height']);
        });
    }
};
