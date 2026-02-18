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
        Schema::create('crossword_clues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crossword_id')->constrained()->onDelete('cascade');
            $table->foreignId('clue_id')->constrained()->onDelete('restrict');
            $table->enum('direction', ['across', 'down']);
            $table->integer('length');
            $table->integer('start_row');
            $table->integer('start_col');

            $table->unique(['crossword_id','direction','start_row','start_col'], 'uq_crossword_clue_position');
            $table->unique(['crossword_id','clue_id'], 'uq_crossword_clue');
            $table->index('crossword_id', 'idx_crossword_clues_crossword_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crossword_clues');
    }
};
