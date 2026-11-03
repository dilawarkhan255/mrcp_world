<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->timestamps();
            $table->index(['status', 'id'], 'feedback_status_index');
            $table->index(['user_id', 'id'], 'feedback_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
