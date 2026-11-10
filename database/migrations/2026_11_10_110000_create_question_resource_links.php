<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learning resources of each MCQ, shown in the right sidebar of the
 * practice screen: the textbook chapters to read and the Image Atlas
 * images that belong to the question, chosen by an editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_textbook_chapter', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('textbook_chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['question_id', 'textbook_chapter_id']);
            $table->index('textbook_chapter_id');
        });

        Schema::create('atlas_image_question', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('atlas_image_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['question_id', 'atlas_image_id']);
            $table->index('atlas_image_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_image_question');
        Schema::dropIfExists('question_textbook_chapter');
    }
};
