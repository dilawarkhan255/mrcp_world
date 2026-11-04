<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leaderboard: students opt in (off by default) and choose a display name, so
 * an email or account name is never shown to other students. The sessions
 * index serves the per-exam leaderboard query, which reads practice and
 * closed mock sessions of one exam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('leaderboard_opt_in')->default(false)->after('timezone');
            $table->string('leaderboard_name', 30)->nullable()->after('leaderboard_opt_in');
            $table->index(['leaderboard_opt_in', 'id'], 'users_leaderboard_index');
        });

        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->index(['exam_id', 'mode', 'completed_at', 'user_id'], 'sessions_leaderboard_index');
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->dropIndex('sessions_leaderboard_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_leaderboard_index');
            $table->dropColumn(['leaderboard_opt_in', 'leaderboard_name']);
        });
    }
};
