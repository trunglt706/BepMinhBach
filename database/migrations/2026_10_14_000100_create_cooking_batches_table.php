<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooking_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->dateTime('starts_at')->index();
            $table->string('location_text')->nullable();
            $table->unsignedInteger('expected_servings');
            $table->unsignedInteger('prepared_servings')->nullable();
            $table->unsignedInteger('distributed_servings')->nullable();
            $table->unsignedBigInteger('estimated_cost_vnd')->nullable();
            $table->foreignId('coordinator_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->text('variance_note')->nullable();
            $table->foreignId('results_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('results_updated_at')->nullable();
            $table->unsignedInteger('results_version')->default(0);
            $table->text('completion_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooking_batches');
    }
};
