<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mã công khai ngẫu nhiên cho URL trang chương trình (/dot-nau/{public_id}) thay cho ID tăng dần:
 * không đoán/dò được chương trình khác bằng cách đổi số trong URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->char('public_id', 16)->nullable()->after('id');
        });

        // Gán mã cho các chương trình đã có.
        DB::table('cooking_batches')->whereNull('public_id')->orderBy('id')->each(function ($row) {
            DB::table('cooking_batches')->where('id', $row->id)->update(['public_id' => bin2hex(random_bytes(8))]);
        });

        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
