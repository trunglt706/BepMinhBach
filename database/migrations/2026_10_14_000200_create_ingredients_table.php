<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_batch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('expected_quantity', 12, 3);
            $table->decimal('actual_quantity', 12, 3)->nullable();
            $table->string('unit', 30);
            $table->boolean('needs_restock')->default(false)->index();
            $table->string('restock_note', 500)->nullable();
            $table->foreignId('restock_reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('restock_reported_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
