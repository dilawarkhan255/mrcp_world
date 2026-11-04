<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A search term for live image searches on Radiopaedia, DermNet, Life in the
 * Fast Lane and EyeWiki (config/student.php "image_search"), and which of
 * those sites suit the question. Null sites means every site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('image_search', 150)->nullable()->after('image_links');
            $table->json('image_search_sites')->nullable()->after('image_search');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['image_search', 'image_search_sites']);
        });
    }
};
