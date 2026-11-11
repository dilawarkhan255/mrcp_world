<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The MCQ list of a subject page (SubjectController): one subject's published
 * MCQs in code order. Without this index the database scans every MCQ of the
 * exam and sorts them; with it, it reads only that subject's rows, already in
 * order.
 */
return new class extends Migration
{
    private const NAME = 'questions_subject_list_index';

    public function up(): void
    {
        if (! Schema::hasIndex('questions', self::NAME)) {
            Schema::table('questions', function (Blueprint $table) {
                $table->index(['category_id', 'is_published', 'deleted_at', 'code', 'id'], self::NAME);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('questions', self::NAME)) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropIndex(self::NAME);
            });
        }
    }
};
