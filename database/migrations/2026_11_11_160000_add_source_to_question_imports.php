<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk uploads can now be a PDF read by Claude (source "pdf") as well as a
 * CSV/XLSX file (source "file").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('question_imports', 'source')) {
            Schema::table('question_imports', function (Blueprint $table) {
                $table->string('source', 10)->default('file')->after('past_paper_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('question_imports', 'source')) {
            Schema::table('question_imports', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
