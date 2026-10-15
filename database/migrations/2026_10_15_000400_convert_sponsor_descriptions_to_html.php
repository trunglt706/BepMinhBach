<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Giới thiệu đơn vị chuyển sang trình soạn thảo (HTML): đổi dữ liệu chữ thuần cũ thành HTML an toàn,
     * mỗi dòng một đoạn, để hiển thị không mất xuống dòng. Trước migration này mọi giới thiệu đều là chữ thuần
     * (kể cả khi chứa ký tự "<"), nên chuyển đổi toàn bộ.
     */
    public function up(): void
    {
        DB::table('sponsors')->whereNotNull('description')->orderBy('id')
            ->each(function ($row) {
                $html = collect(preg_split('/\R/u', trim($row->description)))
                    ->map(fn ($line) => '<div>'.(trim($line) === '' ? '<br>' : htmlspecialchars(trim($line), ENT_QUOTES, 'UTF-8')).'</div>')
                    ->implode('');
                DB::table('sponsors')->where('id', $row->id)->update(['description' => $html]);
            });
    }

    /** Chuyển ngược thành chữ thuần (mỗi đoạn một dòng). */
    public function down(): void
    {
        DB::table('sponsors')->whereNotNull('description')->orderBy('id')->each(function ($row) {
            $text = preg_replace('#<br\s*/?>#i', '', preg_replace('#</(div|p|h[1-6]|li)>#i', "\n", $row->description));
            DB::table('sponsors')->where('id', $row->id)->update([
                'description' => trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null,
            ]);
        });
    }
};
