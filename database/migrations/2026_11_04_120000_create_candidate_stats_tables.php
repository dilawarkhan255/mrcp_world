<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregated answers of every student, refreshed by `php artisan stats:refresh`
 * (scheduled every ten minutes). The practice screen reads one row per
 * question and per subject instead of scanning the answers of all students.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_stats', function (Blueprint $table) {
            $table->foreignId('question_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->unsignedInteger('timed_attempts')->default(0);
            $table->unsignedBigInteger('total_seconds')->default(0);
            $table->json('option_counts')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('category_stats', function (Blueprint $table) {
            $table->foreignId('category_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_stats');
        Schema::dropIfExists('question_stats');
    }
};
