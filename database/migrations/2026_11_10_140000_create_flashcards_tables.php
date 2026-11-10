<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flashcards (front and back, by subject) and each student's spaced
 * repetition schedule for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded: MariaDB does not roll back CREATE TABLE, so a failed run can leave this table behind.
        if (! Schema::hasTable('flashcards')) {
            Schema::create('flashcards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained()->restrictOnDelete();
                $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
                // The MCQ a card was made from, if any.
                $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
                $table->text('front');
                $table->text('back');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_published')->default(false);
                $table->timestamps();
                $table->index(['exam_id', 'is_published', 'category_id', 'sort_order'], 'flashcards_student_index');
            });
        }

        Schema::create('flashcard_reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained()->cascadeOnDelete();
            $table->decimal('ease', 4, 2)->default(2.5);
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedInteger('repetitions')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            // Defaults: MariaDB refuses a second NOT NULL timestamp without one.
            $table->timestamp('due_at')->useCurrent();
            $table->timestamp('reviewed_at')->useCurrent();
            $table->primary(['user_id', 'flashcard_id']);
            $table->index(['user_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_reviews');
        Schema::dropIfExists('flashcards');
    }
};
