<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lý do mở lại chương trình trước đây chỉ nằm trong tóm tắt activity log. Ghi chú tự do không nên vào log,
 * nên lưu ở chương trình (lần mở lại gần nhất); log chỉ ghi "đã mở lại, có lý do".
 */
return new class extends Migration
{
    /**
     * Thêm cột lý do và thời điểm mở lại gần nhất.
     */
    public function up(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->string('reopen_reason', 500)->nullable()->after('cancellation_reason');
            $table->timestamp('reopened_at')->nullable()->after('reopen_reason');
        });
    }

    /**
     * Gỡ hai cột vừa thêm.
     */
    public function down(): void
    {
        Schema::table('cooking_batches', function (Blueprint $table) {
            $table->dropColumn(['reopen_reason', 'reopened_at']);
        });
    }
};
