<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Đơn vị tài trợ / hỗ trợ và các chương trình họ tham gia. */
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('type', 20)->index();
            $table->text('description')->nullable();
            $table->string('website', 255)->nullable();
            $table->string('logo_path')->nullable();
            $table->json('logo_widths')->nullable();
            $table->unsignedInteger('logo_width')->nullable();
            $table->unsignedInteger('logo_height')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('batch_sponsor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('contribution', 200)->nullable();
            $table->timestamps();
            $table->unique(['cooking_batch_id', 'sponsor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_sponsor');
        Schema::dropIfExists('sponsors');
    }
};
