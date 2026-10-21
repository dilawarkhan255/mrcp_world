<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('code', 4)->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('code');
            $table->index(['is_active', 'sort_order'], 'exams_public_index');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('duration_days');
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order'], 'plans_public_index');
        });

        Schema::create('score_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('bucket_start');
            $table->unsignedTinyInteger('bucket_end');
            $table->unsignedInteger('candidates')->default(0);
            $table->timestamps();
            $table->unique(['exam_id', 'bucket_start'], 'score_distribution_bucket_unique');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_published');
            $table->boolean('is_sample')->default(false)->after('is_demo');
            $table->unsignedTinyInteger('difficulty')->default(3)->after('is_sample');
            $table->index(['exam_id', 'is_demo', 'is_published'], 'questions_demo_index');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_demo_index');
            $table->dropColumn(['is_demo', 'is_sample', 'difficulty']);
        });

        Schema::dropIfExists('score_distributions');
        Schema::dropIfExists('plans');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_public_index');
            $table->dropColumn(['description', 'code', 'sort_order']);
        });
    }
};
