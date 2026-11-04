<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image Atlas: clinical images that are content in their own right, separate
 * from MCQs. Every image records where it came from and under which licence,
 * and cannot be published until a person confirms the right to use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qbank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('diagnosis')->nullable();
            $table->string('subject', 100)->nullable();
            $table->string('system', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('source_name')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('license', 30)->default('unknown');
            $table->string('attribution', 500)->nullable();
            $table->boolean('copyright_confirmed')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'subject', 'id'], 'atlas_student_index');
            $table->index(['copyright_confirmed', 'id'], 'atlas_copyright_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_images');
    }
};
