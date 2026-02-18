<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clue_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crossword_clue_id')->constrained()->onDelete('cascade');
            $table->foreignId('crossword_attempt_id')->constrained()->onDelete('cascade');
            $table->string('user_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamp('updated_at')->nullable();

            $table->index(['crossword_attempt_id', 'crossword_clue_id'], 'idx_clue_attempts_attempt_clue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clue_attempts');
    }
};
