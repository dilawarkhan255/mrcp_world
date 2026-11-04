<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bell reads one user's unread notifications of one kind (student or
 * admin panel), so the index covers notifiable, type and read_at. The
 * subscription reminder is sent once per subscription period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'type', 'read_at'], 'notifications_bell_index');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_bell_index');
        });
    }
};
