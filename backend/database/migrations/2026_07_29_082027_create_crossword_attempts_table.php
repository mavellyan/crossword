<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crossword_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('crossword_id')->constrained('crosswords')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');

            // A felhasználó által beírt cellák állapota.
            $table->json('grid_state')->nullable();

            // Több megnyitott böngészőfül miatti felülírások
            // kezelésére használható.
            $table->unsignedInteger('state_version')->default(0);

            $table->timestamp('started_at')->useCurrent();

            $table->timestamp('completed_at')->nullable();

            $table->timestamp('abandoned_at')->nullable();

            $table->timestamps();

            $table->index(
                ['user_id', 'crossword_id', 'status'],
                'attempt_user_crossword_status_index'
            );

            $table->index(
                ['crossword_id', 'status', 'completed_at'],
                'attempt_crossword_leaderboard_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crossword_attempts');
    }
};