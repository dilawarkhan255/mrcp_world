<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->string('mode', 20)->default('practice');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->index(['user_id', 'exam_id', 'mode', 'id'], 'sessions_mode_index');
            $table->index(['mode', 'expires_at'], 'sessions_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->dropIndex('sessions_mode_index');
            $table->dropIndex('sessions_expiry_index');
            $table->dropColumn(['mode', 'duration_seconds', 'started_at', 'expires_at']);
        });
    }
};
