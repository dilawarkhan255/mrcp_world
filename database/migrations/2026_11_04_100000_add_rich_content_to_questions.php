<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich MCQ content. Question, explanation, key points and mnemonics are
 * Markdown (tables and images supported) and are rendered with raw HTML
 * stripped. References hold the citation text an editor wrote; nothing is
 * generated. needs_review flags a question whose citations or content a
 * human must check before it is trusted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('key_points')->nullable()->after('explanation');
            $table->text('mnemonics')->nullable()->after('key_points');
            $table->json('references')->nullable()->after('mnemonics');
            $table->string('youtube_url', 255)->nullable()->after('references');
            $table->json('image_links')->nullable()->after('youtube_url');
            $table->string('system', 100)->nullable()->after('image_links');
            $table->unsignedSmallInteger('repeat_count')->default(0)->after('system');
            $table->string('source_reference', 255)->nullable()->after('repeat_count');
            $table->boolean('needs_review')->default(false)->after('source_reference');
            $table->string('review_note', 500)->nullable()->after('needs_review');
            $table->index(['needs_review', 'exam_id', 'id'], 'questions_review_flag_index');
        });

        // Daily and per-question answer aggregates read answered items by time.
        Schema::table('practice_items', function (Blueprint $table) {
            $table->index(['answered_at'], 'practice_items_answered_at_index');
            $table->index(['question_id', 'answered_at'], 'practice_items_question_answered_index');
        });
    }

    public function down(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->dropIndex('practice_items_question_answered_index');
            $table->dropIndex('practice_items_answered_at_index');
        });
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_review_flag_index');
            $table->dropColumn(['key_points', 'mnemonics', 'references', 'youtube_url', 'image_links', 'system', 'repeat_count', 'source_reference', 'needs_review', 'review_note']);
        });
    }
};
