<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A PDF upload can name the subject for its MCQs (a PDF of one subject, e.g.
 * "Cardiology past papers", has no "Subject:" line per question).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('question_imports', 'category_id')) {
            Schema::table('question_imports', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('past_paper_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('question_imports', 'category_id')) {
            Schema::table('question_imports', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }
    }
};
