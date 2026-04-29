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
            $table->foreignId('crossword_id')->constrained('crosswords')->cascadeOnDelete();
            $table->foreignId('clue_id')->constrained('clues')->cascadeOnDelete();
            $table->enum('direction', ['horizontal', 'vertical']);
            $table->integer('intersection_index')->nullable();
            $table->integer('start_row');
            $table->integer('start_col');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
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
