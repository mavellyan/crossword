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
        Schema::create('crossword_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crossword_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_time_seconds')->nullable();

            $table->index(['crossword_id', 'total_time_seconds', 'completed_at'], 'idx_crossword_attempts_leaderboard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crossword_attempts');
    }
};
