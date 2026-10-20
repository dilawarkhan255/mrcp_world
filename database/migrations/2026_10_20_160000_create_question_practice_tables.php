<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('past_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedSmallInteger('year');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['exam_id', 'slug']);
            $table->index(['exam_id', 'is_published', 'year', 'id'], 'papers_student_index');
        });
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('past_paper_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->text('body');
            $table->json('options');
            $table->char('correct_option', 1);
            $table->text('explanation')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['exam_id', 'code']);
            $table->index('code');
            $table->index(['exam_id', 'is_published', 'deleted_at', 'id'], 'questions_bank_index');
            $table->index(['exam_id', 'is_published', 'category_id', 'deleted_at', 'id'], 'questions_category_index');
            $table->index(['exam_id', 'is_published', 'past_paper_id', 'deleted_at', 'id'], 'questions_paper_index');
        });
        Schema::create('question_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('past_paper_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('path');
            $table->boolean('publish')->default(false);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('last_row')->default(1);
            $table->json('errors')->nullable();
            $table->text('failure')->nullable();
            $table->timestamps();
        });
        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('past_paper_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('answered_count')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'exam_id', 'id'], 'sessions_history_index');
        });
        Schema::create('practice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->json('snapshot');
            $table->char('selected_option', 1)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->unique(['practice_session_id', 'position']);
            $table->index(['practice_session_id', 'answered_at', 'position'], 'session_unanswered_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_items');
        Schema::dropIfExists('practice_sessions');
        Schema::dropIfExists('question_imports');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('past_papers');
    }
};
