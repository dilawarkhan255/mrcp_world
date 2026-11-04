<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A past paper can show its sitting (for example "September"), a short
 * description and a cover image uploaded in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('past_papers', function (Blueprint $table) {
            $table->string('sitting', 40)->nullable()->after('year');
            $table->text('description')->nullable()->after('sitting');
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('past_papers', function (Blueprint $table) {
            $table->dropColumn(['sitting', 'description', 'image_path']);
        });
    }
};
