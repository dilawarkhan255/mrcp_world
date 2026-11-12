<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Knowledge Tutor.
 *
 * - Facts are the flashcards, now with a high-yield rating (set by editors)
 *   and a difficulty (the % of answers "Again", refreshed by stats:refresh).
 * - Each review row also counts the answers and keeps the last rating.
 * - tutor_runs: the filters a student started the tutor with ("Last activity").
 * - tutor_conversations / tutor_messages: the AI tutor's saved history.
 *
 * Nothing existing is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            $table->string('high_yield', 3)->default('A')->after('back');
            $table->unsignedTinyInteger('difficulty')->nullable()->after('high_yield');
            $table->index(['exam_id', 'is_published', 'high_yield'], 'flashcards_tutor_index');
        });

        Schema::table('flashcard_reviews', function (Blueprint $table) {
            $table->unsignedInteger('answers')->default(0)->after('lapses');
            $table->string('last_rating', 5)->nullable()->after('answers');
        });
        // Earlier reviews: at least the answers SM-2 has counted.
        DB::table('flashcard_reviews')->update(['answers' => DB::raw('repetitions + lapses')]);

        Schema::create('tutor_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->json('filters');
            $table->unsignedInteger('answered')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'exam_id', 'updated_at'], 'tutor_runs_recent_index');
        });

        Schema::create('tutor_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->timestamps();
            $table->index(['user_id', 'updated_at'], 'tutor_conversations_recent_index');
        });

        Schema::create('tutor_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('content');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_messages');
        Schema::dropIfExists('tutor_conversations');
        Schema::dropIfExists('tutor_runs');
        Schema::table('flashcard_reviews', function (Blueprint $table) {
            $table->dropColumn(['answers', 'last_rating']);
        });
        Schema::table('flashcards', function (Blueprint $table) {
            $table->dropIndex('flashcards_tutor_index');
            $table->dropColumn(['high_yield', 'difficulty']);
        });
    }
};
