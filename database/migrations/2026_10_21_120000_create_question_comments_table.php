<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('question_comments')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->index(['question_id', 'id'], 'comments_question_index');
            $table->index(['parent_id', 'id'], 'comments_parent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_comments');
    }
};
