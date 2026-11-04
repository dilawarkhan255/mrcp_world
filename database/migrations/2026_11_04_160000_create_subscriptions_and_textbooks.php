<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions: one row per student and Qbank. Payment is manual for now:
 * the student sends a request, an admin activates it for the plan's period.
 * A plan belongs to a Qbank (£8 for 6 months each, from the brochure).
 *
 * Textbook: chapters per exam and subject, unlocked by the subscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('qbank_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qbank_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 10, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->string('student_note', 1000)->nullable();
            $table->string('admin_note', 1000)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            // Access check: an active row of this user and Qbank whose period covers now.
            $table->index(['user_id', 'qbank_id', 'status', 'ends_at'], 'subscriptions_access_index');
            $table->index(['status', 'id'], 'subscriptions_admin_index');
        });

        Schema::create('textbook_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            $table->text('key_points')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['exam_id', 'slug']);
            $table->index(['exam_id', 'is_published', 'category_id', 'sort_order'], 'textbook_student_index');
        });

        $this->seedBrochurePlans();
    }

    /** £8 for 6 months per Qbank. Older plans without a Qbank are switched off, not deleted. */
    private function seedBrochurePlans(): void
    {
        $qbanks = DB::table('qbanks')->orderBy('sort_order')->get(['id', 'name']);
        if ($qbanks->isEmpty()) {
            return; // Fresh install: PlanSeeder creates the plans.
        }
        $now = now();
        foreach ($qbanks as $index => $qbank) {
            if (DB::table('plans')->where('qbank_id', $qbank->id)->exists()) {
                continue;
            }
            DB::table('plans')->insert([
                'qbank_id' => $qbank->id, 'name' => $qbank->name.' — 6 months', 'duration_days' => 180,
                'price' => 8, 'currency' => 'GBP', 'is_active' => true, 'sort_order' => $index + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('plans')->whereNull('qbank_id')->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('textbook_chapters');
        Schema::dropIfExists('subscriptions');
        DB::table('plans')->whereNotNull('qbank_id')->delete();
        DB::table('plans')->update(['is_active' => true]);
        Schema::table('plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qbank_id');
        });
    }
};
