<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->index(['deleted_at', 'id'], 'questions_admin_index'));
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->dropIndex('questions_admin_index'));
    }
};
