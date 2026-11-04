<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Practice screen: a student can mark a question as read, and report an MCQ
 * from the question itself (the report is a feedback row tied to the question).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('answered_at');
            // Seconds the student spent before submitting, measured by the browser and capped server-side.
            $table->unsignedSmallInteger('time_seconds')->nullable()->after('read_at');
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->foreignId('question_id')->nullable()->after('exam_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropConstrainedForeignId('question_id');
        });
        Schema::table('practice_items', function (Blueprint $table) {
            $table->dropColumn(['read_at', 'time_seconds']);
        });
    }
};
