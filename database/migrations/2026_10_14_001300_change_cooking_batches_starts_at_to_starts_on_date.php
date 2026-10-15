<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chương trình chỉ cần NGÀY bắt đầu (yêu cầu 14/10/2026): starts_at (datetime) → starts_on (date).
 * Giữ nguyên ngày của dữ liệu cũ, bỏ phần giờ. Thêm cột mới → chép → xóa cột cũ (chạy được trên MySQL/MariaDB và SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('name');
        });

        DB::table('cooking_batches')->update(['starts_on' => DB::raw('DATE(starts_at)')]);

        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->dropIndex(['starts_at']);
            $table->dropColumn('starts_at');
        });

        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->date('starts_on')->nullable(false)->change();
            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('name');
        });

        DB::table('cooking_batches')->update(['starts_at' => DB::raw('starts_on')]);

        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->dropIndex(['starts_on']);
            $table->dropColumn('starts_on');
        });

        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable(false)->change();
            $table->index('starts_at');
        });
    }
};
